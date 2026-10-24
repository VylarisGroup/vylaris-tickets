<?php

namespace Tests\Feature;

use App\Exceptions\CheckoutException;
use App\Mail\BankTransferMail;
use App\Mail\OrderPaidMail;
use App\Mail\WaitlistMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\OrderService;
use App\Services\TicketPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private Event $event;

    private TicketType $normal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create([
            'name' => 'Verein A', 'slug' => 'verein-a', 'iban' => 'CH9300762011623852957', 'vat_enabled' => true, 'vat_rate' => 8.1,
            'payment_settings' => ['mollie_api_key' => 'test_mollie', 'stripe_secret_key' => 'sk_test_x'],
        ]);

        $this->event = $this->organizer->events()->create([
            'title' => 'Sommerfest', 'slug' => 'sommerfest', 'type' => 'onsite', 'venue_name' => 'Halle', 'status' => 'published',
            'starts_at' => now()->addMonth(), 'currency' => 'CHF',
        ]);

        $this->normal = $this->event->ticketTypes()->create(['name' => 'Normal', 'price_cents' => 2500, 'quota' => 5, 'max_per_order' => 4]);
    }

    private function place(array $items, array $seats = [], string $method = 'bank', ?string $voucher = null, string $email = 'k@example.ch')
    {
        return app(OrderService::class)->place($this->event->fresh(), $items, $seats, ['name' => 'Kim', 'email' => $email], $voucher, $method);
    }

    public function test_event_page_is_public_and_draft_is_hidden(): void
    {
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertOk()->assertSee('Sommerfest')->assertSee('Normal');

        $this->event->update(['status' => 'draft']);
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertNotFound();
    }

    public function test_bank_order_reserves_stock_and_sends_instructions(): void
    {
        Mail::fake();

        $order = $this->place([$this->normal->id => 2]);

        $this->assertSame('pending', $order->status);
        $this->assertSame(5000, $order->total_cents);
        $this->assertSame(2, $order->tickets()->count());
        $this->assertSame(3, $this->normal->fresh()->remaining());
        $this->assertSame(375, $order->vat_cents, 'enthaltene MWST 8.1% von 50.00');
        Mail::assertSent(BankTransferMail::class);
    }

    public function test_cannot_oversell_quota(): void
    {
        $this->place([$this->normal->id => 4]);

        $this->expectException(CheckoutException::class);
        $this->place([$this->normal->id => 2]);
    }

    public function test_sold_out_error_flags_ticket_type_for_waitlist(): void
    {
        $this->place([$this->normal->id => 4]);
        $this->place([$this->normal->id => 1]);

        try {
            $this->place([$this->normal->id => 1]);
            $this->fail('Erwartet: ausverkauft');
        } catch (CheckoutException $e) {
            $this->assertTrue($e->soldOut);
            $this->assertSame($this->normal->id, $e->ticketTypeId);
        }
    }

    public function test_max_per_order_is_enforced_online(): void
    {
        $this->expectException(CheckoutException::class);
        $this->place([$this->normal->id => 5]);
    }

    public function test_expired_orders_release_stock(): void
    {
        $order = $this->place([$this->normal->id => 4]);
        $this->assertSame(1, $this->normal->fresh()->remaining());

        $order->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(5, $this->normal->fresh()->remaining(), 'abgelaufene Bestellung zählt sofort nicht mehr');

        $this->assertSame(1, app(OrderService::class)->expireDue());
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(0, $order->tickets()->where('status', 'valid')->count());
    }

    public function test_mark_paid_is_idempotent_and_sends_tickets_once(): void
    {
        Mail::fake();
        $order = $this->place([$this->normal->id => 1]);

        app(OrderService::class)->markPaid($order);
        app(OrderService::class)->markPaid($order);

        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertSent(OrderPaidMail::class, 1);
    }

    public function test_voucher_discount_and_usage_counter(): void
    {
        $voucher = $this->organizer->vouchers()->create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'max_uses' => 1]);

        $order = $this->place([$this->normal->id => 2], [], 'bank', 'ten');

        $this->assertSame(500, $order->discount_cents);
        $this->assertSame(4500, $order->total_cents);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame(4500, $order->tickets()->sum('price_cents'));

        try {
            $this->place([$this->normal->id => 1], [], 'bank', 'TEN');
            $this->fail('Gutschein darf nur einmal einlösbar sein');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString('Gutschein', $e->getMessage());
        }

        // Verfällt die Bestellung, wird der Gutschein wieder frei.
        app(OrderService::class)->expire($order);
        $this->assertSame(0, $voucher->fresh()->used_count);
    }

    public function test_free_tickets_are_paid_immediately(): void
    {
        Mail::fake();
        $free = $this->event->ticketTypes()->create(['name' => 'Gästeliste', 'price_cents' => 0]);

        $order = $this->place([$free->id => 1], [], '');

        $this->assertSame('paid', $order->status);
        $this->assertSame('free', $order->payment_method);
        Mail::assertSent(OrderPaidMail::class);
    }

    public function test_payment_method_must_be_configured(): void
    {
        $this->organizer->update(['payment_settings' => null, 'iban' => null]);

        $this->expectException(CheckoutException::class);
        $this->place([$this->normal->id => 1], [], 'mollie');
    }

    public function test_checkout_over_http_redirects_to_order_page(): void
    {
        Mail::fake();

        $response = $this->post(route('shop.order', [$this->organizer, $this->event]), [
            'name' => 'Kim', 'email' => 'k@example.ch', 'items' => [$this->normal->id => 1], 'method' => 'bank',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('order.show', $order->access_token));
        $this->get(route('order.show', $order->access_token))->assertOk()->assertSee('CH9300762011623852957')->assertSee($order->number);
    }

    public function test_checkout_errors_are_shown_to_the_customer(): void
    {
        $this->post(route('shop.order', [$this->organizer, $this->event]), [
            'name' => 'Kim', 'email' => 'k@example.ch', 'items' => [$this->normal->id => 0], 'method' => 'bank',
        ])->assertSessionHasErrors('checkout');
    }

    public function test_mollie_payment_flow(): void
    {
        Mail::fake();
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response(['id' => 'tr_123', '_links' => ['checkout' => ['href' => 'https://pay.mollie.test/tr_123']]], 201),
            'api.mollie.com/v2/payments/tr_123' => Http::response(['id' => 'tr_123', 'status' => 'paid']),
        ]);

        $order = $this->place([$this->normal->id => 1], [], 'mollie');

        $this->assertSame('tr_123', $order->provider_ref);
        $this->assertSame('https://pay.mollie.test/tr_123', $order->payment_url);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer test_mollie') && $r['amount']['value'] === '25.00' && $r['amount']['currency'] === 'CHF');

        $this->post(route('webhooks.mollie', $this->organizer), ['id' => 'tr_123'])->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertSent(OrderPaidMail::class);
    }

    public function test_customer_return_page_verifies_payment_with_provider(): void
    {
        Mail::fake();
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/cs_1']),
            'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response(['id' => 'cs_1', 'payment_status' => 'paid', 'status' => 'complete']),
        ]);

        $order = $this->place([$this->normal->id => 1], [], 'stripe');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions' && $r['line_items'][0]['price_data']['unit_amount'] === 2500);

        $this->get(route('order.show', $order->access_token))->assertOk()->assertSee('Bezahlt');
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_forged_webhook_cannot_mark_orders_paid(): void
    {
        Mail::fake();
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response(['id' => 'tr_9', '_links' => ['checkout' => ['href' => 'https://x.test']]], 201),
            'api.mollie.com/v2/payments/tr_9' => Http::response(['id' => 'tr_9', 'status' => 'open']),
        ]);

        $order = $this->place([$this->normal->id => 1], [], 'mollie');
        $this->post(route('webhooks.mollie', $this->organizer), ['id' => 'tr_9'])->assertOk();

        $this->assertSame('pending', $order->fresh()->status, 'Status wird beim Anbieter verifiziert, nicht dem Webhook geglaubt');
    }

    public function test_failed_provider_call_cancels_order_and_reports_error(): void
    {
        Http::fake(['api.mollie.com/*' => Http::response(['error' => 'x'], 500)]);

        try {
            $this->place([$this->normal->id => 1], [], 'mollie');
            $this->fail('Erwartet: CheckoutException');
        } catch (CheckoutException) {
        }

        $this->assertSame(5, $this->normal->fresh()->remaining());
    }

    public function test_refund_calls_provider_and_releases_tickets(): void
    {
        Mail::fake();
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response(['id' => 'tr_5', '_links' => ['checkout' => ['href' => 'https://x.test']]], 201),
            'api.mollie.com/v2/payments/tr_5/refunds' => Http::response(['id' => 're_1'], 201),
        ]);

        $order = $this->place([$this->normal->id => 2], [], 'mollie');
        app(OrderService::class)->markPaid($order);
        app(OrderService::class)->refund($order->fresh());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments/tr_5/refunds'));
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(5, $this->normal->fresh()->remaining());
    }

    public function test_seat_selection_prevents_double_booking(): void
    {
        $this->event->update(['seating_mode' => 'grid']);
        $vip = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 4000]);
        $section = $this->event->sections()->create(['name' => 'Parkett', 'ticket_type_id' => $vip->id]);
        $a1 = $section->seats()->create(['event_id' => $this->event->id, 'row_label' => 'A', 'number' => 1, 'label' => 'A1', 'x' => 40, 'y' => 40]);
        $a2 = $section->seats()->create(['event_id' => $this->event->id, 'row_label' => 'A', 'number' => 2, 'label' => 'A2', 'x' => 70, 'y' => 40]);

        $order = $this->place([], [$a1->id]);
        $this->assertSame(4000, $order->total_cents);
        $this->assertSame($a1->id, $order->tickets()->first()->seat_id);

        try {
            $this->place([], [$a1->id, $a2->id]);
            $this->fail('Platz A1 ist schon vergeben');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString('A1', $e->getMessage());
        }

        $this->assertSame(0, Ticket::where('seat_id', $a2->id)->count(), 'Teilbestellung darf nichts anlegen');

        // Allgemeines Kontingent des Sitzbereichs darf nicht ohne Platzwahl verkauft werden.
        $this->expectException(CheckoutException::class);
        $this->place([$vip->id => 1]);
    }

    public function test_seat_is_released_after_expiry(): void
    {
        $this->event->update(['seating_mode' => 'grid']);
        $type = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 4000]);
        $section = $this->event->sections()->create(['name' => 'P', 'ticket_type_id' => $type->id]);
        $seat = $section->seats()->create(['event_id' => $this->event->id, 'row_label' => 'A', 'number' => 1, 'label' => 'A1', 'x' => 40, 'y' => 40]);

        $order = $this->place([], [$seat->id]);
        $order->update(['expires_at' => now()->subMinute()]);

        $again = $this->place([], [$seat->id]);
        $this->assertSame($seat->id, $again->tickets()->first()->seat_id);
    }

    public function test_blocked_seats_cannot_be_ordered(): void
    {
        $this->event->update(['seating_mode' => 'grid']);
        $type = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 4000]);
        $section = $this->event->sections()->create(['name' => 'P', 'ticket_type_id' => $type->id]);
        $seat = $section->seats()->create(['event_id' => $this->event->id, 'row_label' => 'A', 'number' => 1, 'label' => 'A1', 'x' => 40, 'y' => 40, 'is_blocked' => true]);

        $this->expectException(CheckoutException::class);
        $this->place([], [$seat->id]);
    }

    public function test_waitlist_signup_and_notification_when_stock_frees_up(): void
    {
        Mail::fake();
        $big = $this->place([$this->normal->id => 4]);
        $this->place([$this->normal->id => 1]);

        $this->post(route('shop.waitlist', [$this->organizer, $this->event]), [
            'name' => 'Wanda', 'email' => 'wanda@example.ch', 'ticket_type_id' => $this->normal->id, 'quantity' => 2,
        ])->assertSessionHas('status');
        $this->assertSame(1, WaitlistEntry::count());

        app(OrderService::class)->cancel($big);

        Mail::assertSent(WaitlistMail::class, fn ($m) => $m->hasTo('wanda@example.ch'));
        $this->assertNotNull(WaitlistEntry::first()->notified_at);
    }

    public function test_waitlist_is_not_notified_without_free_stock(): void
    {
        Mail::fake();
        $this->place([$this->normal->id => 4]);
        $this->place([$this->normal->id => 1]);
        WaitlistEntry::create(['event_id' => $this->event->id, 'ticket_type_id' => $this->normal->id, 'name' => 'W', 'email' => 'w@example.ch', 'quantity' => 1]);

        $this->assertSame(0, app(\App\Services\WaitlistService::class)->notifyFor($this->event));
        Mail::assertNotSent(WaitlistMail::class);
    }

    public function test_ticket_pdf_is_generated_with_qr_codes(): void
    {
        Mail::fake();
        $order = $this->place([$this->normal->id => 2]);
        app(OrderService::class)->markPaid($order);

        $pdf = app(TicketPdf::class)->render($order->fresh());

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->get(route('order.pdf', $order->access_token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_is_not_available_for_unpaid_orders(): void
    {
        Mail::fake();
        $order = $this->place([$this->normal->id => 1]);

        $this->get(route('order.pdf', $order->access_token))->assertNotFound();
    }

    public function test_digital_event_gives_access_link_only_after_payment(): void
    {
        Mail::fake();
        $this->event->update(['type' => 'digital', 'online_url' => 'https://stream.example.ch/live', 'venue_name' => null]);

        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertDontSee('stream.example.ch');

        $order = $this->place([$this->normal->id => 1]);
        $this->get(route('order.show', $order->access_token))->assertDontSee('stream.example.ch');

        app(OrderService::class)->markPaid($order);
        $this->get(route('order.show', $order->access_token))->assertSee('https://stream.example.ch/live');
    }

    public function test_reseller_domain_only_shows_own_organizers(): void
    {
        $reseller = \App\Models\Reseller::create(['name' => 'R', 'slug' => 'r', 'domain' => 'tickets.r.ch']);

        $this->get('http://tickets.r.ch/v/verein-a')->assertNotFound();

        $this->organizer->update(['reseller_id' => $reseller->id]);
        $this->get('http://tickets.r.ch/v/verein-a')->assertOk()->assertSee('Sommerfest');
        $this->get('http://tickets.r.ch/')->assertOk()->assertSee('Sommerfest');
    }

    public function test_report_is_stored(): void
    {
        $this->post(route('shop.report', [$this->organizer, $this->event]), ['reason' => 'spam', 'message' => 'Betrug'])->assertSessionHas('status');

        $this->assertSame(1, $this->event->reports()->count());
    }

    public function test_event_page_renders_seat_picker_data(): void
    {
        $this->event->update(['seating_mode' => 'map']);
        $type = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 4000]);
        $section = $this->event->sections()->create(['name' => 'Parkett', 'ticket_type_id' => $type->id]);
        $section->seats()->create(['event_id' => $this->event->id, 'row_label' => 'A', 'number' => 1, 'label' => 'A1', 'x' => 40, 'y' => 40]);

        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertOk()->assertSee('Platzwahl')->assertSee('A1');
    }
}
