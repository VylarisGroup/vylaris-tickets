<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Ticket;
use Illuminate\Http\Request;

/** Auswertung für den ganzen Veranstalter oder ein einzelnes Event. */
class StatsController extends Controller
{
    public function index(Request $request, Organizer $organizer)
    {
        $events = $organizer->events()->orderByDesc('starts_at')->get(['id', 'title', 'slug', 'starts_at', 'currency']);
        $event = $events->firstWhere('slug', (string) $request->query('event'));
        $days = in_array((int) $request->query('tage'), [7, 30, 90, 365], true) ? (int) $request->query('tage') : 30;

        $orders = Order::where('organizer_id', $organizer->id)->where('status', 'paid')
            ->when($event, fn ($q) => $q->where('event_id', $event->id));
        $tickets = Ticket::where('status', 'valid')->whereHas('order', fn ($q) => $q->where('organizer_id', $organizer->id)->where('status', 'paid'))
            ->when($event, fn ($q) => $q->where('event_id', $event->id));

        $totals = [
            'revenue' => (int) (clone $orders)->sum('total_cents'),
            'orders' => (clone $orders)->count(),
            'tickets' => (clone $tickets)->count(),
            'checked_in' => (clone $tickets)->whereNotNull('checked_in_at')->count(),
            'refunded' => Order::where('organizer_id', $organizer->id)->where('status', 'refunded')->when($event, fn ($q) => $q->where('event_id', $event->id))->count(),
            'pending' => Order::where('organizer_id', $organizer->id)->where('status', 'pending')->when($event, fn ($q) => $q->where('event_id', $event->id))->count(),
        ];
        $totals['avg'] = $totals['orders'] ? intdiv($totals['revenue'], $totals['orders']) : 0;

        // Verlauf pro Tag (in PHP gruppiert, damit es auf jeder Datenbank gleich funktioniert)
        $from = now()->subDays($days - 1)->startOfDay();
        $byDay = (clone $orders)->where('paid_at', '>=', $from)->withCount(['tickets as ticket_count' => fn ($q) => $q->where('status', 'valid')])
            ->get(['id', 'paid_at', 'total_cents'])->groupBy(fn ($o) => $o->paid_at->format('Y-m-d'));
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->format('Y-m-d');
            $series[] = ['date' => $d, 'revenue' => (int) ($byDay[$d] ?? collect())->sum('total_cents'), 'tickets' => (int) ($byDay[$d] ?? collect())->sum('ticket_count')];
        }

        $byType = (clone $tickets)->with('ticketType:id,name')->get(['id', 'ticket_type_id', 'price_cents'])->groupBy('ticket_type_id')
            ->map(fn ($g) => ['name' => $g->first()->ticketType?->name ?? '–', 'count' => $g->count(), 'revenue' => (int) $g->sum('price_cents')])
            ->sortByDesc('count')->values();

        $byMethod = (clone $orders)->get(['payment_method', 'channel', 'total_cents'])->groupBy(fn ($o) => Order::methodLabel($o->payment_method))
            ->map(fn ($g, $label) => ['label' => $label, 'count' => $g->count(), 'revenue' => (int) $g->sum('total_cents')])->sortByDesc('revenue')->values();

        $channels = (clone $orders)->get(['channel', 'total_cents'])->groupBy('channel')
            ->map(fn ($g) => ['count' => $g->count(), 'revenue' => (int) $g->sum('total_cents')]);

        $perEvent = $event ? collect() : $organizer->events()->orderByDesc('starts_at')->limit(15)->get()->map(function (Event $e) {
            $sold = Ticket::where('event_id', $e->id)->where('status', 'valid')->whereHas('order', fn ($q) => $q->where('status', 'paid'));
            $capacity = $e->ticketTypes()->get();

            return [
                'event' => $e,
                'sold' => (clone $sold)->count(),
                'checked_in' => (clone $sold)->whereNotNull('checked_in_at')->count(),
                'revenue' => (int) Order::where('event_id', $e->id)->where('status', 'paid')->sum('total_cents'),
                'capacity' => $capacity->contains(fn ($t) => $t->quota === null) ? null : (int) $capacity->sum('quota'),
            ];
        });

        return view('organizer.stats.index', compact('organizer', 'events', 'event', 'days', 'totals', 'series', 'byType', 'byMethod', 'channels', 'perEvent'));
    }
}
