<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Einlass: Tickets per QR-Code oder manuell einlösen. */
class ScanController extends Controller
{
    public function index(Organizer $organizer)
    {
        $events = $organizer->events()->where('status', 'published')->where('type', '!=', 'digital')
            ->where('starts_at', '>=', now()->subDays(2))->orderBy('starts_at')->get();

        return view('organizer.scan.index', compact('organizer', 'events'));
    }

    public function show(Organizer $organizer, Event $event)
    {
        return view('organizer.scan.show', ['organizer' => $organizer, 'event' => $event, 'stats' => $this->stats($event)]);
    }

    /** Löst ein Ticket anhand des gescannten/eingegebenen Codes ein. */
    public function check(Request $request, Organizer $organizer, Event $event): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:200']]);

        $ticket = Ticket::with(['order', 'ticketType', 'seat.section'])
            ->where('event_id', $event->id)->where('code', strtoupper($this->extractCode($data['code'])))->first();

        if (! $ticket) {
            return $this->result('invalid', 'Ungültiger Code', 'Dieses Ticket gehört nicht zu diesem Event.', $event);
        }

        return $this->admit($ticket, $request, $event);
    }

    /** Manuelle Suche nach Name, Bestellnummer oder Code. */
    public function search(Request $request, Organizer $organizer, Event $event): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['tickets' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $tickets = Ticket::with(['order', 'ticketType', 'seat'])->where('event_id', $event->id)
            ->where(function ($w) use ($like, $q) {
                $w->where('holder_name', 'like', $like)->orWhere('code', strtoupper($q))
                    ->orWhereHas('order', fn ($o) => $o->where('number', 'like', $like)->orWhere('customer_name', 'like', $like)->orWhere('customer_email', 'like', $like));
            })->limit(15)->get();

        return response()->json(['tickets' => $tickets->map(fn (Ticket $t) => [
            'id' => $t->id, 'name' => $t->holder_name ?: $t->order->customer_name, 'order' => $t->order->number,
            'type' => $t->ticketType->name, 'seat' => $t->seat?->label,
            'state' => $this->state($t),
            'checked_in_at' => $t->checked_in_at?->format('H:i'),
        ])]);
    }

    /** Löst ein Ticket aus der Suchliste ein. */
    public function admitTicket(Request $request, Organizer $organizer, Event $event, Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->event_id === $event->id, 404);

        return $this->admit($ticket->load(['order', 'ticketType', 'seat.section']), $request, $event);
    }

    /** Macht eine Einlösung rückgängig (nur Verwalter). */
    public function undo(Request $request, Organizer $organizer, Event $event, Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->event_id === $event->id, 404);
        abort_unless($request->user()->canManageOrganizer($organizer), 403);

        $ticket->update(['checked_in_at' => null, 'checked_in_by' => null]);

        return response()->json(['ok' => true, 'stats' => $this->stats($event)]);
    }

    // ---------------------------------------------------------------------------------------------

    private function admit(Ticket $ticket, Request $request, Event $event): JsonResponse
    {
        $state = $this->state($ticket);

        if ($state === 'cancelled') {
            return $this->result('invalid', 'Ticket nicht gültig', 'Die Bestellung wurde storniert oder erstattet.', $event, $ticket);
        }
        if ($state === 'unpaid') {
            return $this->result('invalid', 'Noch nicht bezahlt', 'Zu dieser Bestellung ist keine Zahlung eingegangen.', $event, $ticket);
        }

        // Atomar: nur der erste Scan setzt checked_in_at, parallele Scans sehen 0 betroffene Zeilen.
        $updated = Ticket::whereKey($ticket->id)->whereNull('checked_in_at')
            ->update(['checked_in_at' => now(), 'checked_in_by' => $request->user()->id]);

        if ($updated === 0) {
            $ticket->refresh();

            return $this->result('duplicate', 'Bereits eingelöst', 'Eingelöst um '.$ticket->checked_in_at->format('H:i:s').' Uhr.', $event, $ticket);
        }

        return $this->result('ok', 'Einlass frei', null, $event, $ticket);
    }

    private function state(Ticket $t): string
    {
        if ($t->status !== 'valid' || in_array($t->order->status, ['cancelled', 'expired', 'refunded'], true)) {
            return 'cancelled';
        }
        if (! $t->order->isPaid()) {
            return 'unpaid';
        }

        return $t->checked_in_at ? 'used' : 'valid';
    }

    private function result(string $status, string $title, ?string $detail, Event $event, ?Ticket $ticket = null): JsonResponse
    {
        return response()->json([
            'status' => $status, 'title' => $title, 'detail' => $detail,
            'ticket' => $ticket ? [
                'id' => $ticket->id, 'name' => $ticket->holder_name ?: $ticket->order->customer_name, 'type' => $ticket->ticketType->name,
                'seat' => $ticket->seat ? $ticket->seat->section->name.', '.$ticket->seat->label : null, 'order' => $ticket->order->number,
            ] : null,
            'stats' => $this->stats($event),
        ]);
    }

    private function stats(Event $event): array
    {
        $base = Ticket::where('event_id', $event->id)->where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'));

        return ['total' => (clone $base)->count(), 'checked_in' => (clone $base)->whereNotNull('checked_in_at')->count()];
    }

    /** Akzeptiert den reinen Code oder eine URL, die den Code enthält. */
    private function extractCode(string $input): string
    {
        $input = trim($input);

        return str_contains($input, '/') ? basename(parse_url($input, PHP_URL_PATH) ?: $input) : $input;
    }
}
