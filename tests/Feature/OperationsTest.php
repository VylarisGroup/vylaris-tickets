<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private Event $event;

    private TicketType $type;

    private User $owner;

    private User $cashier;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'vat_enabled' => true, 'vat_rate' => 8.1]);
        $this->owner = User::factory()->create();
        $this->cashier = User::factory()->create();
        $this->scanner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->organizer->users()->attach($this->cashier, ['role' => 'cashier']);
        $this->organizer->users()->attach($this->scanner, ['role' => 'scanner']);

        $this->event = $this->organizer->events()->create([
            'title' => 'Sommerfest', 'slug' => 'sommerfest', 'type' => 'onsite', 'venue_name' => 'Halle', 'status' => 'published',
            'starts_at' => now()->addHours(3), 'currency' => 'CHF', 'door_sales_enabled' => true,
        ]);
        $this->type = $this->event->ticketTypes()->create(['name' => 'Normal', 'price_cents' => 2000, 'quota' => 10]);
    }

    private function openCash(User $user, string $float = '100.00'): CashSession
    {
        $this->actingAs($user)->post(route('organizer.door.open', [$this->organizer, $this->event]), ['opening_float' => $float])->assertRedirect();

        return CashSession::firstOrFail();
    }

    private function sell(User $user, array $extra = [])
    {
        return $this->actingAs($user)->post(route('organizer.door.sell', [$this->organizer, $this->event]), array_merge([
            'items' => [$this->type->id => 2], 'method' => 'cash',
        ], $extra));
    }

    public function test_cashier_opens_session_and_sells_at_the_door(): void
    {
        $this->openCash($this->cashier);
        $this->actingAs($this->cashier)->get(route('organizer.door.show', [$this->organizer, $this->event]))->assertOk()->assertSee('Verkaufen');

        $response = $this->sell($this->cashier);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('organizer.door.receipt', [$this->organizer, $this->event, $order]));
        $this->assertSame('paid', $order->status);
        $this->assertSame('door', $order->channel);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame(4000, $order->total_cents);
        $this->assertSame($this->cashier->id, $order->created_by);
        $this->assertSame(CashSession::first()->id, $order->cash_session_id);

        $this->actingAs($this->cashier)->get(route('organizer.door.receipt', [$this->organizer, $this->event, $order]))->assertOk()->assertSee($order->number);
        $this->actingAs($this->cashier)->get(route('organizer.door.pdf', [$this->organizer, $this->event, $order]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_selling_requires_an_open_cash_session(): void
    {
        $this->sell($this->cashier)->assertStatus(409);
    }

    public function test_scanner_role_cannot_use_the_cash_desk(): void
    {
        $this->actingAs($this->scanner)->get(route('organizer.door.index', $this->organizer))->assertForbidden();
        $this->actingAs($this->scanner)->get(route('organizer.scan.index', $this->organizer))->assertOk();
    }

    public function test_cashier_cannot_manage_but_can_reach_door(): void
    {
        $this->actingAs($this->cashier)->get(route('organizer.events.index', $this->organizer))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('organizer.orders.index', [$this->organizer, $this->event]))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('organizer.door.index', $this->organizer))->assertOk()->assertSee('Sommerfest');
    }

    public function test_door_sales_respect_the_quota_shared_with_online_orders(): void
    {
        $this->openCash($this->cashier);
        $this->sell($this->cashier, ['items' => [$this->type->id => 9]]);

        $this->sell($this->cashier, ['items' => [$this->type->id => 2]])->assertSessionHasErrors('checkout');
        $this->assertSame(1, $this->type->fresh()->remaining());
    }

    public function test_door_sales_can_be_disabled_per_event(): void
    {
        $this->openCash($this->cashier);
        $this->event->update(['door_sales_enabled' => false]);

        $this->sell($this->cashier)->assertSessionHasErrors('checkout');
    }

    public function test_voiding_a_sale_frees_stock_but_only_while_session_is_open(): void
    {
        $session = $this->openCash($this->cashier);
        $this->sell($this->cashier);
        $order = Order::firstOrFail();

        $this->actingAs($this->cashier)->delete(route('organizer.door.void', [$this->organizer, $this->event, $order]))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $this->type->fresh()->remaining());

        $this->sell($this->cashier);
        $second = Order::latest('id')->first();
        $this->actingAs($this->cashier)->post(route('organizer.door.close', [$this->organizer, $this->event, $session]), ['counted_cash' => '140.00']);

        $this->actingAs($this->cashier)->delete(route('organizer.door.void', [$this->organizer, $this->event, $second]))->assertForbidden();
    }

    public function test_cash_closing_calculates_expected_cash_and_difference(): void
    {
        $session = $this->openCash($this->cashier, '100');
        $this->sell($this->cashier);                                   // 40.00 bar
        $this->sell($this->cashier, ['method' => 'external', 'items' => [$this->type->id => 1]]); // 20.00 Karte

        $this->assertSame(14000, $session->fresh()->expectedCashCents(), 'Anfangsbestand + nur Barverkäufe');

        $this->actingAs($this->cashier)->post(route('organizer.door.close', [$this->organizer, $this->event, $session]), ['counted_cash' => '138.50'])
            ->assertRedirect(route('organizer.door.report', [$this->organizer, $this->event, $session]));

        $session->refresh();
        $this->assertFalse($session->isOpen());
        $this->assertSame(13850, $session->counted_cash_cents);

        $this->actingAs($this->cashier)->get(route('organizer.door.report', [$this->organizer, $this->event, $session]))
            ->assertOk()->assertSee('Kassenabschluss')->assertSee('60.00')->assertSee('-1.50');

        $csv = $this->actingAs($this->cashier)->get(route('organizer.door.export', [$this->organizer, $this->event, $session]))->assertOk();
        $this->assertStringContainsString('Abendkasse', $csv->streamedContent());
    }

    public function test_a_cashier_cannot_close_someone_elses_session(): void
    {
        $session = $this->openCash($this->owner);
        $this->actingAs($this->cashier)->post(route('organizer.door.close', [$this->organizer, $this->event, $session]), ['counted_cash' => '1'])->assertForbidden();
    }

    // --- Einlass ----------------------------------------------------------------------------------

    private function paidTicket(): Ticket
    {
        $order = app(OrderService::class)->place($this->event->fresh(), [$this->type->id => 1], [], ['name' => 'Kim Muster', 'email' => 'k@example.ch'], null, 'cash', 'door', $this->cashier);

        return $order->tickets()->firstOrFail();
    }

    private function scan(string $code, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->scanner)->postJson(route('organizer.scan.check', [$this->organizer, $this->event]), ['code' => $code]);
    }

    public function test_scanning_admits_once_and_flags_duplicates(): void
    {
        $ticket = $this->paidTicket();

        $this->scan($ticket->code)->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('ticket.name', 'Kim Muster')->assertJsonPath('stats.checked_in', 1);
        $this->scan($ticket->code)->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame($this->scanner->id, $ticket->fresh()->checked_in_by);
    }

    public function test_phone_pairing_scans_and_pc_display_receives_the_result(): void
    {
        $ticket = $this->paidTicket();

        $this->actingAs($this->scanner)->get(route('organizer.scan.display', [$this->organizer, $this->event]))->assertOk()->assertSee('Kopplung starten');
        $this->actingAs($this->scanner)->post(route('organizer.scan.display.pair', [$this->organizer, $this->event]))->assertRedirect();
        $pairing = \App\Models\ScanPairing::firstOrFail();
        $this->actingAs($this->scanner)->get(route('organizer.scan.display', [$this->organizer, $this->event]))->assertOk()->assertSee($pairing->code);

        // Smartphone (ohne Login) öffnet die Scanner-Seite per Token und scannt
        auth()->logout();
        $this->get(route('scanner.show', $pairing->token))->assertOk()->assertSee('Kamera starten');
        $this->postJson(route('scanner.check', $pairing->token), ['code' => $ticket->code])->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('stats.checked_in', 1);
        $this->postJson(route('scanner.check', $pairing->token), ['code' => $ticket->code])->assertJsonPath('status', 'duplicate');
        $this->assertSame($this->scanner->id, $ticket->fresh()->checked_in_by);

        // PC-Anzeige holt die Ergebnisse
        $this->actingAs($this->scanner)->getJson(route('organizer.scan.display.feed', [$this->organizer, $this->event, 'after' => 0, 'pairing' => $pairing->id]))
            ->assertOk()->assertJsonCount(2, 'logs')->assertJsonPath('logs.0.status', 'ok')->assertJsonPath('connected', true)->assertJsonPath('stats.checked_in', 1);

        // Beitritt per Code, danach Trennen sperrt das Gerät
        $this->post(route('scanner.join'), ['code' => $pairing->code])->assertRedirect(route('scanner.show', $pairing->token));
        $this->post(route('scanner.join'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($this->scanner)->delete(route('organizer.scan.display.revoke', [$this->organizer, $this->event, $pairing]))->assertRedirect();
        auth()->logout();
        $this->postJson(route('scanner.check', $pairing->token), ['code' => $ticket->code])->assertNotFound();
        $this->get(route('scanner.show', $pairing->token))->assertNotFound();
    }

    public function test_scanner_page_rejects_unknown_tokens(): void
    {
        $this->get(route('scanner.show', 'gibtsnicht'))->assertNotFound();
        $this->get(route('scanner.entry'))->assertOk();
    }

    public function test_scan_accepts_lowercase_and_url_wrapped_codes(): void
    {
        $ticket = $this->paidTicket();

        $this->scan('https://tickets.example.ch/t/'.strtolower($ticket->code))->assertJsonPath('status', 'ok');
    }

    public function test_unknown_foreign_and_cancelled_tickets_are_rejected(): void
    {
        $this->scan('NOPE')->assertJsonPath('status', 'invalid');

        $ticket = $this->paidTicket();
        $other = $this->organizer->events()->create(['title' => 'Anderes', 'slug' => 'anderes', 'type' => 'onsite', 'venue_name' => 'X', 'status' => 'published', 'starts_at' => now()->addDay(), 'currency' => 'CHF']);
        $this->actingAs($this->scanner)->postJson(route('organizer.scan.check', [$this->organizer, $other]), ['code' => $ticket->code])->assertJsonPath('status', 'invalid');

        app(OrderService::class)->refund($ticket->order);
        $this->scan($ticket->code)->assertJsonPath('status', 'invalid');
        $this->assertNull($ticket->fresh()->checked_in_at);
    }

    public function test_unpaid_orders_are_not_admitted(): void
    {
        $this->organizer->update(['iban' => 'CH9300762011623852957']);
        $order = app(OrderService::class)->place($this->event->fresh(), [$this->type->id => 1], [], ['name' => 'Bank', 'email' => 'b@example.ch'], null, 'bank');

        $this->scan($order->tickets()->first()->code)->assertJsonPath('status', 'invalid')->assertJsonPath('title', 'Noch nicht bezahlt');
    }

    public function test_manual_search_admission_and_undo_permissions(): void
    {
        $ticket = $this->paidTicket();

        $this->actingAs($this->scanner)->getJson(route('organizer.scan.search', [$this->organizer, $this->event, 'q' => 'muster']))
            ->assertOk()->assertJsonPath('tickets.0.state', 'valid');

        $this->actingAs($this->scanner)->postJson(route('organizer.scan.admit', [$this->organizer, $this->event, $ticket]))->assertJsonPath('status', 'ok');
        $this->actingAs($this->scanner)->postJson(route('organizer.scan.undo', [$this->organizer, $this->event, $ticket]))->assertForbidden();
        $this->actingAs($this->owner)->postJson(route('organizer.scan.undo', [$this->organizer, $this->event, $ticket]))->assertOk();
        $this->assertNull($ticket->fresh()->checked_in_at);
    }

    public function test_scanner_page_renders(): void
    {
        $this->actingAs($this->scanner)->get(route('organizer.scan.show', [$this->organizer, $this->event]))->assertOk()->assertSee('Kamera starten');
    }

    public function test_other_organizers_staff_cannot_scan_here(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->postJson(route('organizer.scan.check', [$this->organizer, $this->event]), ['code' => 'X'])->assertForbidden();
    }

    // --- Bestellverwaltung --------------------------------------------------------------------------

    public function test_owner_manages_bank_orders(): void
    {
        $this->organizer->update(['iban' => 'CH9300762011623852957']);
        $order = app(OrderService::class)->place($this->event->fresh(), [$this->type->id => 2], [], ['name' => 'Bank', 'email' => 'b@example.ch'], null, 'bank');

        $this->actingAs($this->owner)->get(route('organizer.orders.index', [$this->organizer, $this->event]))->assertOk()->assertSee($order->number);
        $this->actingAs($this->owner)->get(route('organizer.orders.show', [$this->organizer, $this->event, $order]))->assertOk()->assertSee('Als bezahlt markieren');

        $this->actingAs($this->owner)->post(route('organizer.orders.paid', [$this->organizer, $this->event, $order]))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->status);

        $this->actingAs($this->owner)->post(route('organizer.orders.refund', [$this->organizer, $this->event, $order]))->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(10, $this->type->fresh()->remaining());
    }

    public function test_order_export_and_foreign_order_access(): void
    {
        $this->paidTicket();

        $csv = $this->actingAs($this->owner)->get(route('organizer.orders.export', [$this->organizer, $this->event]))->assertOk();
        $this->assertStringContainsString('Kim Muster', $csv->streamedContent());

        $otherEvent = $this->organizer->events()->create(['title' => 'B', 'slug' => 'b', 'type' => 'onsite', 'venue_name' => 'X', 'starts_at' => now()->addDay(), 'currency' => 'CHF']);
        $this->actingAs($this->owner)->get(route('organizer.orders.show', [$this->organizer, $otherEvent, Order::first()]))->assertNotFound();
    }

    // --- Melden -------------------------------------------------------------------------------------

    public function test_platform_admin_can_block_event_after_report(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $report = Report::create(['event_id' => $this->event->id, 'reason' => 'spam', 'message' => 'Betrug']);
        Report::create(['event_id' => $this->event->id, 'reason' => 'other']);

        $this->actingAs($this->owner)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Betrug');

        $this->actingAs($admin)->patch(route('admin.reports.update', $report), ['action' => 'block', 'reason' => 'Betrugsverdacht'])->assertRedirect();

        $this->assertTrue($this->event->fresh()->is_blocked);
        $this->assertSame(0, Report::where('status', 'open')->count());

        // Gesperrtes Event ist nicht kaufbar und nicht öffentlich sichtbar.
        auth()->logout();
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertNotFound();

        $this->actingAs($admin)->patch(route('admin.reports.update', $report), ['action' => 'unblock'])->assertRedirect();
        $this->assertFalse($this->event->fresh()->is_blocked);
    }

    // --- Befehle & Seiten ----------------------------------------------------------------------------

    public function test_expire_command_releases_orders(): void
    {
        $this->organizer->update(['iban' => 'CH9300762011623852957']);
        $order = app(OrderService::class)->place($this->event->fresh(), [$this->type->id => 2], [], ['name' => 'Bank', 'email' => 'b@example.ch'], null, 'bank');
        $order->update(['expires_at' => now()->subMinute()]);

        $this->artisan('tickets:expire-orders')->assertSuccessful();
        $this->assertSame('expired', $order->fresh()->status);
    }

    public function test_dashboards_render_for_all_roles(): void
    {
        foreach ([$this->owner, $this->cashier, $this->scanner] as $user) {
            $this->actingAs($user)->get(route('organizer.dashboard', $this->organizer))->assertOk()->assertSee('Sommerfest');
        }

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->owner)->get(route('organizer.events.show', [$this->organizer, $this->event]))->assertOk()->assertSee('Bestellungen');
    }
}
