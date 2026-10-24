<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Teilnehmerliste pro Event: suchen, exportieren, manuell einlösen. */
class AttendeeController extends Controller
{
    public function index(Request $request, Organizer $organizer, Event $event)
    {
        $tickets = $this->query($request, $event)->orderBy('holder_name')->paginate(40)->withQueryString();

        $base = Ticket::where('event_id', $event->id)->admittable();

        return view('organizer.attendees.index', [
            'organizer' => $organizer, 'event' => $event, 'tickets' => $tickets,
            'total' => (clone $base)->count(), 'checkedIn' => (clone $base)->whereNotNull('checked_in_at')->count(),
        ]);
    }

    public function export(Request $request, Organizer $organizer, Event $event): StreamedResponse
    {
        $tickets = $this->query($request, $event)->orderBy('holder_name')->get();

        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'E-Mail', 'Ticket', 'Platz', 'Code', 'Bestellung', 'Eingelöst', 'Ausgang'], ';');
            foreach ($tickets as $t) {
                fputcsv($out, [
                    $t->holder_name, $t->order->customer_email, $t->ticketType->name,
                    $t->seat ? $t->seat->section->name.' '.$t->seat->label : '', $t->code, $t->order->number,
                    $t->checked_in_at?->format('d.m.Y H:i') ?? '', $t->checked_out_at?->format('d.m.Y H:i') ?? '',
                ], ';');
            }
            fclose($out);
        }, 'teilnehmende-'.$event->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Einlösen oder zurücksetzen (Umschalten), atomar. */
    public function toggle(Organizer $organizer, Event $event, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->event_id === $event->id, 404);

        if ($ticket->checked_in_at) {
            $ticket->update(['checked_in_at' => null, 'checked_in_by' => null, 'checked_out_at' => null]);

            return back()->with('status', $ticket->holder_name.': Einlass zurückgesetzt.');
        }

        $ok = Ticket::whereKey($ticket->id)->admittable()->whereNull('checked_in_at')
            ->update(['checked_in_at' => now(), 'checked_in_by' => request()->user()->id]);

        return $ok
            ? back()->with('status', $ticket->holder_name.' wurde eingelassen.')
            : back()->withErrors(['ticket' => 'Dieses Ticket ist nicht gültig oder die Bestellung ist nicht bezahlt.']);
    }

    private function query(Request $request, Event $event)
    {
        $q = Ticket::where('tickets.event_id', $event->id)->admittable()->with(['order', 'ticketType', 'seat.section']);

        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where(fn ($w) => $w->where('holder_name', 'like', $like)->orWhere('code', 'like', $like)
                ->orWhereHas('order', fn ($o) => $o->where('number', 'like', $like)->orWhere('customer_email', 'like', $like)->orWhere('customer_name', 'like', $like)));
        }
        match ($request->query('status')) {
            'in' => $q->whereNotNull('checked_in_at'),
            'out' => $q->whereNull('checked_in_at'),
            default => null,
        };

        return $q;
    }
}
