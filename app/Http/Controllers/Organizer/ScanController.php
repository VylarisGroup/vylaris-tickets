<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use App\Services\CheckinService;
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
    public function check(Request $request, Organizer $organizer, Event $event, CheckinService $checkin): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:200']]);

        return response()->json($checkin->checkCode($event, $data['code'], $request->user()->id));
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
    public function admitTicket(Request $request, Organizer $organizer, Event $event, Ticket $ticket, CheckinService $checkin): JsonResponse
    {
        abort_unless($ticket->event_id === $event->id, 404);

        return response()->json($checkin->admit($event, $ticket->load(['order', 'ticketType', 'seat.section']), $request->user()->id));
    }

    /** Macht eine Einlösung rückgängig (nur Verwalter). */
    public function undo(Request $request, Organizer $organizer, Event $event, Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->event_id === $event->id, 404);
        abort_unless($request->user()->canManageOrganizer($organizer), 403);

        $ticket->update(['checked_in_at' => null, 'checked_in_by' => null]);

        return response()->json(['ok' => true, 'stats' => $this->stats($event)]);
    }

    private function state(Ticket $t): string
    {
        return app(CheckinService::class)->state($t);
    }

    private function stats(Event $event): array
    {
        return app(CheckinService::class)->stats($event);
    }
}
