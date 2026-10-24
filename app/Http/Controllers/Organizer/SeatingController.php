<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\SeatingMode;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Seat;
use App\Models\SeatSection;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeatingController extends Controller
{
    private const MAX_SEATS = 3000;

    public function show(Organizer $organizer, Event $event)
    {
        $event->load('ticketTypes', 'sections.ticketType');

        return view('organizer.events.seating', [
            'organizer' => $organizer,
            'event' => $event,
            'editor' => $event->seating_mode === SeatingMode::Map ? $this->editorState($event) : null,
            'seatCounts' => $event->seats()->selectRaw('seat_section_id, count(*) c')->groupBy('seat_section_id')->pluck('c', 'seat_section_id'),
        ]);
    }

    public function mode(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $data = $request->validate(['seating_mode' => ['required', Rule::enum(SeatingMode::class)]]);
        $mode = SeatingMode::from($data['seating_mode']);

        if (! $mode->isSeated() && Ticket::where('event_id', $event->id)->whereNotNull('seat_id')->exists()) {
            return back()->withErrors(['seating_mode' => 'Es gibt schon Bestellungen mit Platzwahl. Die Platzwahl kann nicht mehr abgeschaltet werden.']);
        }

        if (! $mode->isSeated()) {
            $event->sections()->delete();
        }

        $event->update(['seating_mode' => $mode]);

        return back()->with('status', 'Modus: '.$mode->label().'.');
    }

    /** Raster-Modus: einen Bereich mit Reihen und Plätzen erzeugen. */
    public function storeSection(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        abort_unless($event->seating_mode === SeatingMode::Grid, 422);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
            'rows' => ['required', 'integer', 'min:1', 'max:60'],
            'seats_per_row' => ['required', 'integer', 'min:1', 'max:80'],
            'first_row' => ['required', 'regex:/^[A-Za-z]{1,2}$|^[0-9]{1,3}$/'],
        ], ['first_row.regex' => 'Erste Reihe: ein Buchstabe (A) oder eine Zahl (1).']);

        if ($event->seats()->count() + $data['rows'] * $data['seats_per_row'] > self::MAX_SEATS) {
            return back()->withInput()->withErrors(['rows' => 'Mehr als '.self::MAX_SEATS.' Plätze pro Event sind nicht möglich.']);
        }

        DB::transaction(function () use ($event, $data) {
            $section = $event->sections()->create([
                'name' => $data['name'], 'ticket_type_id' => $data['ticket_type_id'], 'sort' => ($event->sections()->max('sort') ?? 0) + 1,
            ]);

            $baseY = $event->seats()->exists() ? $event->seats()->max('y') + 80 : 40;
            $rowLabel = strtoupper($data['first_row']);

            for ($r = 0; $r < $data['rows']; $r++) {
                $label = $this->rowLabel($rowLabel, $r);

                for ($n = 1; $n <= $data['seats_per_row']; $n++) {
                    $section->seats()->create([
                        'event_id' => $event->id,
                        'row_label' => $label,
                        'number' => $n,
                        'label' => $this->uniqueLabel($event, $section->name.' '.$label.$n, $label.$n),
                        'x' => 40 + ($n - 1) * 30,
                        'y' => $baseY + $r * 34,
                    ]);
                }
            }
        });

        return back()->with('status', 'Bereich wurde mit '.($data['rows'] * $data['seats_per_row']).' Plätzen angelegt.');
    }

    public function updateSection(Request $request, Organizer $organizer, Event $event, SeatSection $section): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
        ]);

        $section->update($data);

        return back()->with('status', 'Bereich wurde gespeichert.');
    }

    public function destroySection(Organizer $organizer, Event $event, SeatSection $section): RedirectResponse
    {
        if (Ticket::whereIn('seat_id', $section->seats()->select('id'))->exists()) {
            return back()->withErrors(['section' => 'Für diesen Bereich gibt es schon Bestellungen. Er kann nicht gelöscht werden.']);
        }

        $section->delete();

        return back()->with('status', 'Bereich wurde gelöscht.');
    }

    /** Raster-Modus: einzelne Plätze sperren/freigeben (z. B. Technikpult, Rollstuhlplätze). */
    public function toggleSeat(Organizer $organizer, Event $event, Seat $seat): RedirectResponse
    {
        $seat->update(['is_blocked' => ! $seat->is_blocked]);

        return back();
    }

    /** Saalplan-Editor: kompletten Plan speichern. */
    public function saveMap(Request $request, Organizer $organizer, Event $event): JsonResponse
    {
        abort_unless($event->seating_mode === SeatingMode::Map, 422);

        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:30'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.name' => ['required', 'string', 'max:80'],
            'sections.*.ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
            'seats' => ['present', 'array', 'max:'.self::MAX_SEATS],
            'seats.*.id' => ['nullable', 'integer'],
            'seats.*.section' => ['required', 'integer', 'min:0'],
            'seats.*.label' => ['required', 'string', 'max:40'],
            'seats.*.row_label' => ['nullable', 'string', 'max:10'],
            'seats.*.number' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seats.*.x' => ['required', 'numeric', 'min:0', 'max:20000'],
            'seats.*.y' => ['required', 'numeric', 'min:0', 'max:20000'],
            'seats.*.is_blocked' => ['boolean'],
        ]);

        $labels = array_map(fn ($s) => mb_strtolower(trim($s['label'])), $data['seats']);
        if (count($labels) !== count(array_unique($labels))) {
            throw ValidationException::withMessages(['seats' => 'Platzbezeichnungen müssen eindeutig sein.']);
        }
        foreach ($data['seats'] as $s) {
            if (! isset($data['sections'][$s['section']])) {
                throw ValidationException::withMessages(['seats' => 'Ein Platz gehört zu keinem Bereich.']);
            }
        }

        DB::transaction(function () use ($event, $data) {
            // Bereiche abgleichen
            $existingSections = $event->sections()->get()->keyBy('id');
            $sectionIds = [];

            foreach ($data['sections'] as $i => $sec) {
                $model = isset($sec['id']) ? $existingSections->get($sec['id']) : null;
                $attrs = ['name' => $sec['name'], 'ticket_type_id' => $sec['ticket_type_id'], 'sort' => $i + 1];
                $model ? $model->update($attrs) : $model = $event->sections()->create($attrs);
                $sectionIds[$i] = $model->id;
            }

            $removedSections = $existingSections->keys()->diff(array_values($sectionIds));

            // Plätze abgleichen
            $existingSeats = $event->seats()->get()->keyBy('id');
            $keep = [];

            // Erst Labels der bestehenden Plätze freimachen, damit Umbenennungen/Vertauschungen den Unique-Index nicht verletzen.
            foreach ($existingSeats as $seat) {
                $seat->forceFill(['label' => '~'.$seat->id.'~'.mb_substr($seat->label, 0, 20)])->saveQuietly();
            }

            foreach ($data['seats'] as $s) {
                $attrs = [
                    'seat_section_id' => $sectionIds[$s['section']],
                    'row_label' => $s['row_label'] ?? null,
                    'number' => $s['number'] ?? 0,
                    'label' => trim($s['label']),
                    'x' => (int) round($s['x']),
                    'y' => (int) round($s['y']),
                    'is_blocked' => (bool) ($s['is_blocked'] ?? false),
                ];

                $model = isset($s['id']) ? $existingSeats->get($s['id']) : null;
                $model ? $model->update($attrs) : $model = $event->seats()->create($attrs);
                $keep[] = $model->id;
            }

            // Plätze, die im Plan fehlen, nur löschen wenn nie bestellt.
            $drop = $existingSeats->keys()->diff($keep);
            $locked = Ticket::whereIn('seat_id', $drop)->distinct()->pluck('seat_id');

            if ($locked->isNotEmpty()) {
                $labels = $existingSeats->whereIn('id', $locked)->map(fn ($s) => preg_replace('/^~\d+~/', '', $s->label))->take(5)->implode(', ');

                throw ValidationException::withMessages(['seats' => "Plätze mit Bestellungen können nicht entfernt werden ({$labels}). Sperre sie stattdessen."]);
            }

            $event->seats()->whereIn('id', $drop)->delete();
            $event->sections()->whereIn('id', $removedSections)->delete();
        });

        return response()->json(['ok' => true, 'state' => $this->editorState($event->fresh())]);
    }

    private function editorState(Event $event): array
    {
        return [
            'saveUrl' => route('organizer.seating.map', [$event->organizer, $event]),
            'ticketTypes' => $event->ticketTypes()->get(['id', 'name', 'price_cents'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'sections' => $event->sections()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'ticket_type_id'])->values(),
            'seats' => $event->seats()->orderBy('id')->get()->map(fn ($s) => [
                'id' => $s->id, 'section_id' => $s->seat_section_id, 'label' => $s->label, 'row_label' => $s->row_label,
                'number' => $s->number, 'x' => $s->x, 'y' => $s->y, 'is_blocked' => $s->is_blocked,
                'sold' => Ticket::where('seat_id', $s->id)->exists(),
            ])->values(),
        ];
    }

    /** A, B … Z, AA … bzw. 1, 2, 3 … ab dem Startwert. */
    private function rowLabel(string $first, int $offset): string
    {
        if (ctype_digit($first)) {
            return (string) ((int) $first + $offset);
        }

        $n = 0;
        foreach (str_split($first) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        $n += $offset;

        $label = '';
        while ($n > 0) {
            $n--;
            $label = chr(65 + $n % 26).$label;
            $n = intdiv($n, 26);
        }

        return $label;
    }

    private function uniqueLabel(Event $event, string $preferred, string $short): string
    {
        // Kurzform «A5» wenn frei, sonst mit Bereichsnamen.
        return $event->seats()->where('label', $short)->exists() ? $preferred : $short;
    }
}
