<?php

namespace App\Http\Controllers;

use App\Models\PartyComment;
use App\Models\PartyGuest;
use App\Models\PartyItem;
use App\Services\PartyCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Öffentliche Einladungsseite. Der Zugang erfolgt ausschliesslich über den persönlichen Link des Gastes. */
class PartyGuestController extends Controller
{
    public function show(string $token)
    {
        $guest = $this->guest($token);
        $party = $guest->party->load(['organizer', 'items.guest', 'comments']);
        $answers = $party->guests()->whereNotNull('response')->get();

        return view('party.show', [
            'party' => $party,
            'guest' => $guest,
            'attending' => $answers->where('response', 'yes'),
            'maybe' => $answers->where('response', 'maybe')->count(),
            'declined' => $answers->where('response', 'no')->count(),
        ]);
    }

    public function rsvp(Request $request, string $token): RedirectResponse
    {
        $guest = $this->guest($token);
        $party = $guest->party;
        if ($party->rsvpClosed() || $party->isOver()) {
            return back()->with('error_status', 'Die Antwortfrist ist abgelaufen.');
        }

        $data = $request->validate([
            'response' => ['required', 'in:yes,maybe,no'],
            'companions' => ['nullable', 'integer', 'min:0', 'max:'.max($party->max_companions, 0)],
            'companion_names' => ['nullable', 'array', 'max:10'],
            'companion_names.*' => ['nullable', 'string', 'max:120'],
            'answers' => ['nullable', 'array'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $yes = $data['response'] === 'yes';
        $companions = $party->allow_companions && $yes ? (int) ($data['companions'] ?? 0) : 0;
        $names = array_values(array_filter(array_map(fn ($n) => trim((string) $n), array_slice($data['companion_names'] ?? [], 0, $companions))));
        if (count($names) < $companions) {
            return back()->withInput()->withErrors(['companion_names' => 'Bitte gib den Namen jeder Begleitperson an.']);
        }

        $answers = [];
        if ($data['response'] !== 'no') {
            foreach ($party->questions ?? [] as $q) {
                $v = trim((string) ($data['answers'][$q['id']] ?? ''));
                if ($v === '' || ($q['options'] && ! in_array($v, $q['options'], true))) {
                    continue;
                }
                $answers[$q['id']] = mb_substr($v, 0, 200);
            }
        }

        $guest->update([
            'response' => $data['response'],
            'companions' => $companions,
            'companion_names' => $companions ? $names : null,
            'answers' => $answers ?: null,
            'message' => $data['message'] ?? null,
            'responded_at' => now(),
        ]);

        if ($guest->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($guest->email)->send(new \App\Mail\PartyResponseMail($party->load('organizer.reseller'), $guest));
            } catch (\Throwable $e) {
                report($e); // Die Antwort ist gespeichert, nur die Bestätigung ging nicht raus.
            }
        }

        return back()->with('status', match ($data['response']) {
            'yes' => 'Danke, du hast zugesagt!',
            'maybe' => 'Danke, wir haben «Vielleicht» vermerkt.',
            default => 'Schade, wir haben deine Absage vermerkt.',
        });
    }

    public function calendar(string $token)
    {
        $guest = $this->guest($token);

        return response(app(PartyCalendar::class)->ics($guest->party->load('organizer'), $guest), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="einladung.ics"',
        ]);
    }

    public function claim(string $token, int $item): RedirectResponse
    {
        $guest = $this->guest($token);
        abort_unless($guest->party->allow_items, 404);
        $row = PartyItem::where('party_id', $guest->party_id)->findOrFail($item);

        // Atomar übernehmen: nur wenn noch niemand den Eintrag hat.
        $taken = PartyItem::whereKey($row->id)->whereNull('guest_id')->update(['guest_id' => $guest->id]);

        return back()->with($taken ? 'status' : 'error_status', $taken ? 'Danke, du bringst «'.$row->label.'» mit.' : 'Das hat gerade jemand anderes übernommen.');
    }

    public function release(string $token, int $item): RedirectResponse
    {
        $guest = $this->guest($token);
        $row = PartyItem::where('party_id', $guest->party_id)->where('guest_id', $guest->id)->findOrFail($item);
        $row->added_by_guest ? $row->delete() : $row->update(['guest_id' => null]);

        return back();
    }

    public function addItem(Request $request, string $token): RedirectResponse
    {
        $guest = $this->guest($token);
        abort_unless($guest->party->allow_items, 404);
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);
        // Pro Gast höchstens 10 eigene Einträge, damit die Liste nicht zugemüllt wird.
        if (PartyItem::where('party_id', $guest->party_id)->where('guest_id', $guest->id)->where('added_by_guest', true)->count() >= 10) {
            return back()->with('error_status', 'Du kannst höchstens 10 eigene Einträge hinzufügen.');
        }
        PartyItem::create(['party_id' => $guest->party_id, 'label' => $data['label'], 'guest_id' => $guest->id, 'added_by_guest' => true]);

        return back()->with('status', 'Zur Mitbringliste hinzugefügt.');
    }

    public function comment(Request $request, string $token): RedirectResponse
    {
        $guest = $this->guest($token);
        abort_unless($guest->party->allow_comments, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        PartyComment::create(['party_id' => $guest->party_id, 'guest_id' => $guest->id, 'author' => $guest->name, 'body' => $data['body']]);

        return back();
    }

    private function guest(string $token): PartyGuest
    {
        return PartyGuest::with('party')->where('token', $token)->firstOrFail();
    }
}
