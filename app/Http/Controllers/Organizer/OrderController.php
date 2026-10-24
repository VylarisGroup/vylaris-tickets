<?php

namespace App\Http\Controllers\Organizer;

use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Bestellverwaltung und Auswertung pro Event (nur Inhaber/Verwalter). */
class OrderController extends Controller
{
    public function index(Request $request, Organizer $organizer, Event $event)
    {
        $orders = $this->filtered($request, $event)->withCount(['tickets as ticket_count' => fn ($q) => $q->where('status', 'valid')])->latest('id')->paginate(30)->withQueryString();

        return view('organizer.orders.index', [
            'organizer' => $organizer, 'event' => $event, 'orders' => $orders,
            'stats' => $this->stats($event),
        ]);
    }

    /** Alle Bestellungen des Veranstalters, über alle Events. */
    public function all(Request $request, Organizer $organizer)
    {
        $q = Order::where('organizer_id', $organizer->id)->with('event:id,title,slug');

        if ($eventSlug = $request->query('event')) {
            $q->whereHas('event', fn ($e) => $e->where('slug', $eventSlug));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('customer_name', 'like', $like)->orWhere('customer_email', 'like', $like));
        }

        return view('organizer.orders.all', [
            'organizer' => $organizer,
            'orders' => $q->withCount(['tickets as ticket_count' => fn ($t) => $t->where('status', 'valid')])->latest('id')->paginate(30)->withQueryString(),
            'events' => $organizer->events()->orderByDesc('starts_at')->get(['id', 'title', 'slug']),
        ]);
    }

    public function show(Organizer $organizer, Event $event, Order $order)
    {
        $this->guard($event, $order);

        return view('organizer.orders.show', ['organizer' => $organizer, 'event' => $event, 'order' => $order->load('tickets.ticketType', 'tickets.seat', 'voucher', 'creator')]);
    }

    public function paid(Organizer $organizer, Event $event, Order $order, OrderService $orders): RedirectResponse
    {
        $this->guard($event, $order);
        abort_unless($order->isPending() || in_array($order->status, ['expired'], true), 409);

        $order = $orders->markPaid($order, $order->payment_method === 'bank' ? 'bank' : null);

        return back()->with('status', $order->isPaid() ? 'Als bezahlt markiert, Tickets wurden versendet.' : 'Die Plätze sind nicht mehr verfügbar, die Bestellung konnte nicht reaktiviert werden.');
    }

    public function cancel(Organizer $organizer, Event $event, Order $order, OrderService $orders): RedirectResponse
    {
        $this->guard($event, $order);
        abort_unless($order->isPending(), 409);

        $orders->cancel($order);

        return back()->with('status', 'Bestellung storniert.');
    }

    public function refund(Organizer $organizer, Event $event, Order $order, OrderService $orders): RedirectResponse
    {
        $this->guard($event, $order);
        abort_unless($order->isPaid(), 409);

        try {
            $orders->refund($order);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['order' => 'Die Rückerstattung beim Zahlungsanbieter ist fehlgeschlagen. Bitte direkt im Dashboard des Anbieters prüfen.']);
        }

        return back()->with('status', 'Bestellung erstattet und Plätze freigegeben.');
    }

    public function resend(Organizer $organizer, Event $event, Order $order, OrderService $orders): RedirectResponse
    {
        $this->guard($event, $order);
        abort_unless($order->customer_email, 422);

        $orders->resend($order);

        return back()->with('status', 'E-Mail wurde erneut gesendet.');
    }

    public function export(Request $request, Organizer $organizer, Event $event)
    {
        return DoorController::csv('bestellungen-'.$event->slug.'.csv', $this->filtered($request, $event)->with('tickets')->orderBy('id')->get());
    }

    // ---------------------------------------------------------------------------------------------

    private function filtered(Request $request, Event $event)
    {
        $q = Order::where('event_id', $event->id);

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($channel = $request->query('channel')) {
            $q->where('channel', $channel);
        }
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('customer_name', 'like', $like)->orWhere('customer_email', 'like', $like));
        }

        return $q;
    }

    private function stats(Event $event): array
    {
        $paid = Order::where('event_id', $event->id)->where('status', 'paid');
        $tickets = \App\Models\Ticket::where('event_id', $event->id)->where('status', 'valid')->whereHas('order', fn ($q) => $q->where('status', 'paid'));

        return [
            'revenue' => (int) (clone $paid)->sum('total_cents'),
            'online' => (int) (clone $paid)->where('channel', 'online')->sum('total_cents'),
            'door' => (int) (clone $paid)->where('channel', 'door')->sum('total_cents'),
            'orders' => (clone $paid)->count(),
            'pending' => Order::where('event_id', $event->id)->where('status', 'pending')->count(),
            'tickets' => (clone $tickets)->count(),
            'checked_in' => (clone $tickets)->whereNotNull('checked_in_at')->count(),
        ];
    }

    private function guard(Event $event, Order $order): void
    {
        abort_unless($order->event_id === $event->id, 404);
    }
}
