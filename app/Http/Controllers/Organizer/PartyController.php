<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Mail\PartyInvitationMail;
use App\Models\Organizer;
use App\Models\Party;
use App\Models\PartyComment;
use App\Models\PartyGuest;
use App\Models\PartyItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Einladungen (ohne Ticketverkauf): Gästeliste, Versand, Zu-/Absagen, Mitbringliste, Kommentare. */
class PartyController extends Controller
{
    public function index(Organizer $organizer)
    {
        return view('organizer.parties.index', [
            'organizer' => $organizer,
            'parties' => $organizer->parties()->with('guests')->orderByDesc('starts_at')->get(),
        ]);
    }

    public function create(Organizer $organizer)
    {
        return view('organizer.parties.form', [
            'organizer' => $organizer,
            'party' => new Party(['theme' => 'ocean', 'font' => 'modern', 'allow_companions' => true, 'max_companions' => 2, 'allow_comments' => true, 'host_name' => $organizer->name]),
        ]);
    }

    public function store(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $this->handleCover($request, $this->validated($request));
        $party = $organizer->parties()->create($data);

        return redirect()->route('organizer.parties.show', [$organizer, $party])->with('status', 'Einladung angelegt. Füge jetzt Gäste hinzu.');
    }

    public function show(Organizer $organizer, Party $party)
    {
        $party->load(['guests', 'items.guest', 'comments']);

        return view('organizer.parties.show', ['organizer' => $organizer, 'party' => $party, 'counts' => $party->counts()]);
    }

    public function edit(Organizer $organizer, Party $party)
    {
        return view('organizer.parties.form', ['organizer' => $organizer, 'party' => $party]);
    }

    public function update(Request $request, Organizer $organizer, Party $party): RedirectResponse
    {
        $party->update($this->handleCover($request, $this->validated($request), $party));

        return redirect()->route('organizer.parties.show', [$organizer, $party])->with('status', 'Einladung gespeichert.');
    }

    public function destroy(Organizer $organizer, Party $party): RedirectResponse
    {
        if ($party->cover_path) {
            Storage::disk('public')->delete($party->cover_path);
        }
        $party->delete();

        return redirect()->route('organizer.parties.index', $organizer)->with('status', 'Einladung gelöscht.');
    }

    /** Gäste hinzufügen: eine Person pro Zeile («Name; E-Mail», «Name, E-Mail» oder nur eine E-Mail). */
    public function addGuests(Request $request, Organizer $organizer, Party $party): RedirectResponse
    {
        $request->validate(['guests' => ['required', 'string', 'max:20000']]);
        $existing = $party->guests()->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e))->all();
        $added = 0;
        $skipped = 0;

        foreach (preg_split('/\R/u', (string) $request->input('guests')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $email] = $this->parseGuestLine($line);
            if ($name === '' || ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $skipped++;

                continue;
            }
            if ($email !== null && in_array(mb_strtolower($email), $existing, true)) {
                $skipped++;

                continue;
            }
            $party->guests()->create(['name' => mb_substr($name, 0, 120), 'email' => $email]);
            if ($email !== null) {
                $existing[] = mb_strtolower($email);
            }
            $added++;
        }

        $msg = $added.' Gast'.($added === 1 ? '' : 'e').' hinzugefügt.'.($skipped ? " {$skipped} Zeile(n) übersprungen (leer, doppelt oder ungültige E-Mail)." : '');

        return back()->with($added ? 'status' : 'error_status', $msg);
    }

    /** @return array{0:string,1:?string} */
    private function parseGuestLine(string $line): array
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/[;\t,]/u', $line)), fn ($p) => $p !== ''));
        $email = null;
        $name = [];
        foreach ($parts as $part) {
            if ($email === null && str_contains($part, '@') && ! str_contains($part, ' ')) {
                $email = $part;
            } else {
                $name[] = $part;
            }
        }
        $name = implode(' ', $name);
        if ($name === '' && $email !== null) {
            $name = Str::title(str_replace(['.', '_'], ' ', strstr($email, '@', true)));
        }

        return [$name, $email];
    }

    public function destroyGuest(Organizer $organizer, Party $party, PartyGuest $guest): RedirectResponse
    {
        abort_unless($guest->party_id === $party->id, 404);
        $guest->delete();

        return back()->with('status', 'Gast entfernt.');
    }

    /** Einladung per E-Mail an alle, die noch keine bekommen haben (oder gezielt an einen Gast erneut). */
    public function send(Request $request, Organizer $organizer, Party $party): RedirectResponse
    {
        $guests = $party->guests()->whereNotNull('email');
        if ($request->filled('guest')) {
            $guests->whereKey((int) $request->input('guest'));
        } else {
            $guests->whereNull('invited_at');
        }

        return $this->deliver($party, $guests->get(), false, 'Einladung');
    }

    /** Erinnerung an Gäste, die noch nicht geantwortet haben oder «Vielleicht» gewählt haben. */
    public function remind(Organizer $organizer, Party $party): RedirectResponse
    {
        $guests = $party->guests()->whereNotNull('email')->whereNotNull('invited_at')
            ->where(fn ($q) => $q->whereNull('response')->orWhere('response', 'maybe'))->get();

        return $this->deliver($party, $guests, true, 'Erinnerung');
    }

    private function deliver(Party $party, $guests, bool $reminder, string $what): RedirectResponse
    {
        $party->load('organizer.reseller');
        $sent = 0;
        $failed = 0;
        foreach ($guests as $guest) {
            try {
                Mail::to($guest->email)->send(new PartyInvitationMail($party, $guest, $reminder));
                $guest->update($reminder ? ['reminded_at' => now()] : ['invited_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        if ($sent === 0 && $failed === 0) {
            return back()->with('error_status', 'Niemand zu benachrichtigen: Es gibt keine passenden Gäste mit E-Mail-Adresse.');
        }

        return back()->with($failed ? 'error_status' : 'status', "{$what}: {$sent} gesendet".($failed ? ", {$failed} fehlgeschlagen (siehe Mail-Einstellungen)." : '.'));
    }

    public function exportGuests(Organizer $organizer, Party $party): StreamedResponse
    {
        $guests = $party->guests()->get();

        return response()->streamDownload(function () use ($guests) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'E-Mail', 'Antwort', 'Begleitpersonen', 'Nachricht', 'Geantwortet am', 'Link'], ';');
            foreach ($guests as $g) {
                fputcsv($out, [$g->name, $g->email, $g->responseLabel(), $g->response === 'yes' ? $g->companions : '', $g->message, $g->responded_at?->format('d.m.Y H:i'), $g->url()], ';');
            }
            fclose($out);
        }, 'gaesteliste-'.Str::slug($party->title).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeItem(Request $request, Organizer $organizer, Party $party): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);
        $party->items()->create(['label' => $data['label']]);

        return back()->with('status', 'Zur Mitbringliste hinzugefügt.');
    }

    public function destroyItem(Organizer $organizer, Party $party, PartyItem $item): RedirectResponse
    {
        abort_unless($item->party_id === $party->id, 404);
        $item->delete();

        return back();
    }

    public function storeComment(Request $request, Organizer $organizer, Party $party): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $party->comments()->create(['author' => $party->hostLabel(), 'by_host' => true, 'body' => $data['body']]);

        return back();
    }

    public function destroyComment(Organizer $organizer, Party $party, PartyComment $comment): RedirectResponse
    {
        abort_unless($comment->party_id === $party->id, 404);
        $comment->delete();

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'host_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'venue_name' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', 'in:'.implode(',', array_keys(Party::THEMES))],
            'font' => ['required', 'in:'.implode(',', array_keys(Party::FONTS))],
            'max_companions' => ['nullable', 'integer', 'min:1', 'max:10'],
            'rsvp_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        unset($data['cover']);
        $data['allow_companions'] = $request->boolean('allow_companions');
        $data['max_companions'] = (int) ($data['max_companions'] ?? 2);
        $data['allow_items'] = $request->boolean('allow_items');
        $data['allow_comments'] = $request->boolean('allow_comments');

        return $data;
    }

    private function handleCover(Request $request, array $data, ?Party $party = null): array
    {
        if ($request->hasFile('cover')) {
            if ($party?->cover_path) {
                Storage::disk('public')->delete($party->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store('party-covers', 'public');
        } elseif ($party && $request->boolean('remove_cover') && $party->cover_path) {
            Storage::disk('public')->delete($party->cover_path);
            $data['cover_path'] = null;
        }

        return $data;
    }
}
