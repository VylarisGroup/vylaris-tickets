<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\SeatingMode;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Seat;
use App\Models\SeatFloor;
use App\Models\SeatSection;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeatingController extends Controller
{
    private const MAX_SEATS = 3000;

    public function show(Organizer $organizer, Event $event)
    {
        $event->load('ticketTypes', 'sections.ticketType');

        if ($event->seating_mode === SeatingMode::Map) {
            $this->ensureFloor($event);
        }

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
            foreach ($event->floors as $floor) {
                $floor->plan_path && Storage::disk('public')->delete($floor->plan_path);
            }
            $event->floors()->delete();
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
        abort_unless($section->event_id === $event->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
        ]);

        $section->update($data);

        return back()->with('status', 'Bereich wurde gespeichert.');
    }

    public function destroySection(Organizer $organizer, Event $event, SeatSection $section): RedirectResponse
    {
        abort_unless($section->event_id === $event->id, 404);
        if (Ticket::whereIn('seat_id', $section->seats()->select('id'))->exists()) {
            return back()->withErrors(['section' => 'Für diesen Bereich gibt es schon Bestellungen. Er kann nicht gelöscht werden.']);
        }

        $section->delete();

        return back()->with('status', 'Bereich wurde gelöscht.');
    }

    /** Raster-Modus: einzelne Plätze sperren/freigeben (z. B. Technikpult, Rollstuhlplätze). */
    public function toggleSeat(Organizer $organizer, Event $event, Seat $seat): RedirectResponse
    {
        abort_unless($seat->event_id === $event->id, 404);
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
            'seats.*.floor_id' => ['nullable', Rule::exists('seat_floors', 'id')->where('event_id', $event->id)],
            'seats.*.kind' => ['nullable', Rule::in(['seat', 'table'])],
            'seats.*.capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'seats.*.table_mode' => ['nullable', Rule::in(['whole', 'seats'])],
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

        $defaultFloor = $this->ensureFloor($event);

        DB::transaction(function () use ($event, $data, $defaultFloor) {
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
                    'seat_floor_id' => (int) ($s['floor_id'] ?? $defaultFloor->id),
                    'kind' => $s['kind'] ?? 'seat',
                    'capacity' => ($s['kind'] ?? 'seat') === 'table' ? (int) ($s['capacity'] ?? 4) : 1,
                    'table_mode' => $s['table_mode'] ?? 'whole',
                ];

                $model = isset($s['id']) ? $existingSeats->get($s['id']) : null;

                if ($model) {
                    $this->assertBookingsStillFit($model, $attrs);
                }

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

    // --- Ebenen (Stockwerke/Räume) und Hallenplan --------------------------------------------

    public function storeFloor(Request $request, Organizer $organizer, Event $event): JsonResponse
    {
        abort_unless($event->seating_mode === SeatingMode::Map, 422);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        if ($event->floors()->count() >= 20) {
            throw ValidationException::withMessages(['name' => 'Mehr als 20 Ebenen sind nicht möglich.']);
        }

        $floor = $event->floors()->create(['name' => $data['name'], 'sort' => ($event->floors()->max('sort') ?? 0) + 1]);

        return response()->json(['floor' => $this->floorData($floor)]);
    }

    public function updateFloor(Request $request, Organizer $organizer, Event $event, SeatFloor $floor): JsonResponse
    {
        abort_unless($floor->event_id === $event->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $floor->update($data);

        return response()->json(['floor' => $this->floorData($floor)]);
    }

    public function destroyFloor(Organizer $organizer, Event $event, SeatFloor $floor): JsonResponse
    {
        abort_unless($floor->event_id === $event->id, 404);

        if ($event->floors()->count() <= 1) {
            throw ValidationException::withMessages(['floor' => 'Mindestens eine Ebene ist nötig.']);
        }
        if (Ticket::whereIn('seat_id', $floor->seats()->select('id'))->exists()) {
            throw ValidationException::withMessages(['floor' => 'Für Plätze dieser Ebene gibt es schon Bestellungen. Sie kann nicht gelöscht werden.']);
        }

        $floor->plan_path && Storage::disk('public')->delete($floor->plan_path);
        $floor->delete();

        return response()->json(['ok' => true]);
    }

    /** Hallenplan hochladen (Bild; PDFs wandelt der Browser vorher in ein Bild um). Wird normalisiert und verkleinert gespeichert. */
    public function uploadPlan(Request $request, Organizer $organizer, Event $event, SeatFloor $floor): JsonResponse
    {
        abort_unless($floor->event_id === $event->id, 404);
        $request->validate(['plan' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360']], ['plan.mimes' => 'Bitte ein Bild (JPG, PNG, WebP) oder ein PDF hochladen.']);

        $path = $request->file('plan')->getRealPath();
        $info = @getimagesize($path);
        if (! $info || $info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessages(['plan' => 'Das Bild ist ungültig oder zu gross.']);
        }

        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            throw ValidationException::withMessages(['plan' => 'Das Bild konnte nicht gelesen werden.']);
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 2400 / $w);
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, 86);
        $jpg = (string) ob_get_clean();

        $new = 'plans/'.Str::random(32).'.jpg';
        Storage::disk('public')->put($new, $jpg);
        $floor->plan_path && Storage::disk('public')->delete($floor->plan_path);
        $floor->update(['plan_path' => $new, 'plan_w' => $nw, 'plan_h' => $nh]);

        return response()->json(['floor' => $this->floorData($floor)]);
    }

    public function removePlan(Organizer $organizer, Event $event, SeatFloor $floor): JsonResponse
    {
        abort_unless($floor->event_id === $event->id, 404);
        $floor->plan_path && Storage::disk('public')->delete($floor->plan_path);
        $floor->update(['plan_path' => null, 'plan_w' => null, 'plan_h' => null]);

        return response()->json(['floor' => $this->floorData($floor)]);
    }

    private function floorData(SeatFloor $f): array
    {
        return ['id' => $f->id, 'name' => $f->name, 'plan' => $f->planUrl(), 'h' => $f->canvasHeight(), 'uploadUrl' => route('organizer.seating.floors.plan', [$f->event->organizer, $f->event, $f]), 'url' => route('organizer.seating.floors.update', [$f->event->organizer, $f->event, $f])];
    }

    /** Stellt sicher, dass es mindestens eine Ebene gibt; bestehende Plätze ohne Ebene wandern hinein. */
    private function ensureFloor(Event $event): SeatFloor
    {
        $floor = $event->floors()->first() ?? $event->floors()->create(['name' => 'Erdgeschoss', 'sort' => 1]);
        $event->seats()->whereNull('seat_floor_id')->update(['seat_floor_id' => $floor->id]);

        return $floor;
    }

    /** Verhindert, dass eine Änderung am Plan bestehende Buchungen ungültig macht (Tischgrösse, Tischmodus, Einzelplatz ↔ Tisch). */
    private function assertBookingsStillFit(Seat $seat, array $attrs): void
    {
        $held = Ticket::holding()->where('tickets.seat_id', $seat->id)->count();

        if ($held === 0) {
            return;
        }
        if ($attrs['kind'] !== $seat->kind || $attrs['table_mode'] !== $seat->table_mode) {
            throw ValidationException::withMessages(['seats' => "«{$seat->label}» hat Bestellungen: Art und Buchungsmodus lassen sich nicht mehr ändern."]);
        }
        if ($attrs['capacity'] < $seat->capacity && $attrs['capacity'] < $held) {
            throw ValidationException::withMessages(['seats' => "«{$seat->label}» hat {$held} gebuchte Plätze und kann nicht kleiner werden."]);
        }
    }

    private function editorState(Event $event): array
    {
        return [
            'saveUrl' => route('organizer.seating.map', [$event->organizer, $event]),
            'ticketTypes' => $event->ticketTypes()->get(['id', 'name', 'price_cents'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'floorUrl' => route('organizer.seating.floors.store', [$event->organizer, $event]),
            'floors' => $event->floors()->get()->map(fn ($f) => $this->floorData($f))->values(),
            'sections' => $event->sections()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'ticket_type_id'])->values(),
            'seats' => $event->seats()->orderBy('id')->get()->map(fn ($s) => [
                'id' => $s->id, 'section_id' => $s->seat_section_id, 'floor_id' => $s->seat_floor_id, 'label' => $s->label, 'row_label' => $s->row_label,
                'number' => $s->number, 'x' => $s->x, 'y' => $s->y, 'is_blocked' => $s->is_blocked,
                'kind' => $s->kind, 'capacity' => $s->capacity, 'table_mode' => $s->table_mode,
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
