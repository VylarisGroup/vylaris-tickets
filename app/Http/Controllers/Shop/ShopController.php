<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\WaitlistEntry;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    private const SECTION_COLORS = ['#1867f8', '#0e9f6e', '#d97706', '#9333ea', '#e11d48', '#0891b2', '#65a30d', '#c2410c'];

    public function organizer(Organizer $organizer)
    {
        $this->guardOrganizer($organizer);

        return view('shop.organizer', [
            'organizer' => $organizer,
            'events' => $organizer->events()->where('status', 'published')->where('is_blocked', false)
                ->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->get(),
        ]);
    }

    public function event(Request $request, Organizer $organizer, Event $event)
    {
        $this->guardEvent($request, $organizer, $event);

        $event->load('ticketTypes');
        $isOnSale = $event->isOnSale();
        $mode = $event->seating_mode;

        $types = $event->ticketTypes->where('is_active', true)->map(function ($t) {
            $remaining = $t->remaining();

            return [
                'id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'price_cents' => $t->price_cents,
                'price' => $t->price(), 'max' => min($t->max_per_order, $remaining ?? $t->max_per_order),
                'sold_out' => $remaining === 0, 'seated' => $t->isSeated(),
                'on_sale' => (! $t->sales_start_at || $t->sales_start_at->lte(now())) && (! $t->sales_end_at || $t->sales_end_at->gte(now())),
            ];
        })->values();

        return view('shop.event', [
            'organizer' => $organizer,
            'event' => $event,
            'types' => $types,
            'isOnSale' => $isOnSale,
            'methods' => $organizer->onlinePaymentMethods(),
            'seatData' => $mode->isSeated() ? $this->seatData($event) : null,
            'reasons' => Report::REASONS,
        ]);
    }

    public function order(Request $request, Organizer $organizer, Event $event, OrderService $orders): RedirectResponse
    {
        $this->guardEvent($request, $organizer, $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'seats' => ['nullable', 'array'],
            'seats.*' => ['integer'],
            'voucher' => ['nullable', 'string', 'max:40'],
            'method' => ['nullable', 'string', 'max:12'],
        ]);

        try {
            $order = $orders->place(
                $event,
                $data['items'] ?? [],
                $data['seats'] ?? [],
                ['name' => $data['name'], 'email' => $data['email']],
                $data['voucher'] ?? null,
                $data['method'] ?? '',
            );
        } catch (CheckoutException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()])->with('sold_out_type', $e->soldOut ? $e->ticketTypeId : null);
        }

        // Zu Mollie/Stripe weiterleiten, sonst direkt zur Bestellseite.
        if ($order->isPending() && in_array($order->payment_method, ['mollie', 'stripe'], true) && $order->payment_url) {
            return redirect()->away($order->payment_url);
        }

        return redirect()->route('order.show', $order->access_token);
    }

    public function waitlist(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $this->guardEvent($request, $organizer, $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'ticket_type_id' => ['nullable', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        WaitlistEntry::firstOrCreate(
            ['event_id' => $event->id, 'email' => strtolower($data['email']), 'ticket_type_id' => $data['ticket_type_id'] ?? null, 'notified_at' => null],
            ['name' => $data['name'], 'quantity' => $data['quantity']],
        );

        return back()->with('status', 'Du stehst auf der Warteliste. Wir schreiben dir, sobald Tickets frei werden.');
    }

    public function report(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $this->guardEvent($request, $organizer, $event);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Report::REASONS))],
            'message' => ['nullable', 'string', 'max:2000'],
            'reporter_email' => ['nullable', 'email', 'max:190'],
        ]);

        $event->reports()->create($data);

        return back()->with('status', 'Danke für deine Meldung. Wir prüfen den Hinweis.');
    }

    private function guardOrganizer(Organizer $organizer): void
    {
        $reseller = app('tenant.reseller');

        abort_unless($organizer->is_active, 404);
        abort_if($reseller && $organizer->reseller_id !== $reseller->id, 404);
    }

    private function guardEvent(Request $request, Organizer $organizer, Event $event): void
    {
        $this->guardOrganizer($organizer);

        $isManager = $request->user()?->canManageOrganizer($organizer);

        // Entwürfe und gesperrte Events sieht nur das Veranstalter-Team.
        abort_if(($event->status->value === 'draft' || $event->is_blocked) && ! $isManager, 404);
    }

    private function seatData(Event $event): array
    {
        $sections = $event->sections()->with('ticketType')->get();
        $colors = [];
        foreach ($sections as $i => $s) {
            $colors[$s->id] = self::SECTION_COLORS[$i % count(self::SECTION_COLORS)];
        }

        $taken = Ticket::holding()->where('event_id', $event->id)->whereNotNull('seat_id')->pluck('seat_id')->flip();

        return [
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'color' => $colors[$s->id],
                'price' => $s->ticketType?->price(), 'ticket' => $s->ticketType?->name, 'price_cents' => $s->ticketType?->price_cents ?? 0,
                'sellable' => (bool) $s->ticketType?->is_active,
            ])->values(),
            'seats' => $event->seats()->where('is_blocked', false)->orderBy('id')->get()->map(fn ($s) => [
                'id' => $s->id, 'label' => $s->label, 'x' => $s->x, 'y' => $s->y, 'section' => $s->seat_section_id,
                'taken' => $taken->has($s->id),
            ])->values(),
        ];
    }
}
