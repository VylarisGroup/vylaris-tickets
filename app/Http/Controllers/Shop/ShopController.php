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

    public function organizer(Organizer $organizer)
    {
        $this->guardOrganizer($organizer);

        return view('shop.organizer', [
            'organizer' => $organizer,
            'events' => $organizer->events()->where('status', 'published')->where('is_blocked', false)->where('is_private', false)
                ->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->get(),
        ]);
    }

    public function event(Request $request, Organizer $organizer, Event $event)
    {
        $this->guardEvent($request, $organizer, $event);

        if ($this->isLocked($request, $event)) {
            return response()->view('shop.locked', ['organizer' => $organizer, 'event' => $event]);
        }

        $event->load('ticketTypes', 'sponsors');
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
        abort_if($this->isLocked($request, $event), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'accept_terms' => $organizer->hasLegalLinks() ? ['accepted'] : ['nullable'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'seats' => ['nullable', 'array'],
            'seats.*' => ['regex:/^\d+(x\d{1,3})?$/'],
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

        if ($organizer->hasLegalLinks()) {
            $order->forceFill(['terms_accepted_at' => now()])->save();
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
        abort_if($this->isLocked($request, $event), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'ticket_type_id' => ['nullable', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $entry = WaitlistEntry::firstOrCreate(
            ['event_id' => $event->id, 'email' => strtolower($data['email']), 'ticket_type_id' => $data['ticket_type_id'] ?? null, 'notified_at' => null],
            ['name' => $data['name'], 'quantity' => $data['quantity']],
        );

        if ($entry->wasRecentlyCreated) {
            app(\App\Services\Push\PushNotifier::class)->toOrganizer(
                $organizer, 'waitlist', 'Neue Warteliste-Anmeldung · '.$organizer->name,
                $entry->name.' · '.$entry->quantity.'× · '.$event->title, route('organizer.events.show', [$organizer->slug, $event->slug], false), 'waitlist-'.$event->id,
            );
        }

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

        app(\App\Services\Push\PushNotifier::class)->toAdmins('report', 'Neue Meldung', $event->title.': '.(Report::REASONS[$data['reason']] ?? $data['reason']), route('admin.reports.index', [], false), 'report');

        return back()->with('status', 'Danke für deine Meldung. Wir prüfen den Hinweis.');
    }

    /** Zugangsschlüssel prüfen und das Event für diese Sitzung freischalten. */
    public function unlock(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $this->guardEvent($request, $organizer, $event);
        $data = $request->validate(['key' => ['required', 'string', 'max:60']]);

        if (! $event->access_key || ! hash_equals((string) $event->access_key, trim($data['key']))) {
            return back()->withErrors(['key' => 'Der Zugangsschlüssel ist nicht korrekt.']);
        }

        $this->unlockForSession($request, $event);

        return redirect()->route('shop.event', [$organizer, $event]);
    }

    /** Kalendereintrag (.ics) für das Event. */
    public function calendar(Request $request, Organizer $organizer, Event $event)
    {
        $this->guardEvent($request, $organizer, $event);
        abort_if($this->isLocked($request, $event), 403);

        return response(app(\App\Services\CalendarExport::class)->ics($event), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }

    private function isLocked(Request $request, Event $event): bool
    {
        if (! $event->access_key) {
            return false;
        }
        if ($request->user()?->canManageOrganizer($event->organizer)) {
            return false;
        }

        // Link mit ?zugang=SCHLÜSSEL schaltet direkt frei.
        $given = $request->query('zugang');
        if (is_string($given) && hash_equals((string) $event->access_key, $given)) {
            $this->unlockForSession($request, $event);
        }

        return ! in_array($event->id, $request->session()->get('unlocked_events', []), true);
    }

    private function unlockForSession(Request $request, Event $event): void
    {
        $request->session()->put('unlocked_events', array_values(array_unique([...$request->session()->get('unlocked_events', []), $event->id])));
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
        return app(\App\Services\SeatMapData::class)->for($event);
    }
}
