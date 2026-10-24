<?php

namespace Tests\Feature;

use App\Mail\EventReminderMail;
use App\Mail\SalesReportMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use App\Services\CheckinService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventfrogFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'contact_email' => 'info@verein.ch', 'iban' => 'CH9300762011623852957']);
        $this->owner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->event = $this->organizer->events()->create([
            'title' => 'Sommerfest, gross; mit Komma', 'slug' => 'fest', 'type' => 'onsite', 'venue_name' => 'Halle', 'venue_address' => 'Weg 1, Bern',
            'starts_at' => now()->addDay(), 'currency' => 'CHF', 'status' => 'published',
        ]);
        $this->event->ticketTypes()->create(['name' => 'Standard', 'price_cents' => 2000, 'max_per_order' => 10, 'sort' => 1, 'is_active' => true]);
    }

    private function paidOrder(string $email = 'k@example.ch'): Order
    {
        $order = app(OrderService::class)->place($this->event->fresh(), [$this->event->ticketTypes()->first()->id => 2], [], ['name' => 'Kim Muster', 'email' => $email], null, 'bank');

        return app(OrderService::class)->markPaid($order);
    }

    public function test_private_event_is_hidden_from_lists_and_needs_the_key(): void
    {
        $this->event->update(['is_private' => true, 'access_key' => 'geheim']);

        $this->get('/')->assertDontSee('Sommerfest');
        $this->get(route('shop.organizer', $this->organizer))->assertDontSee('Sommerfest');

        $url = route('shop.event', [$this->organizer, $this->event]);
        $this->get($url)->assertOk()->assertSee('Zugangsschlüssel')->assertDontSee('Deine Angaben');
        $this->post(route('shop.unlock', [$this->organizer, $this->event]), ['key' => 'falsch'])->assertSessionHasErrors('key');
        $this->get(route('shop.calendar', [$this->organizer, $this->event]))->assertForbidden();
        $this->post(route('shop.order', [$this->organizer, $this->event]), ['name' => 'A', 'email' => 'a@b.ch'])->assertForbidden();

        $this->post(route('shop.unlock', [$this->organizer, $this->event]), ['key' => 'geheim'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Sommerfest')->assertSee('noindex');
    }

    public function test_access_key_in_link_unlocks_and_private_without_key_is_open(): void
    {
        $this->event->update(['is_private' => true, 'access_key' => 'geheim']);
        $this->get(route('shop.event', [$this->organizer, $this->event, 'zugang' => 'geheim']))->assertOk()->assertSee('Sommerfest');

        $this->event->update(['access_key' => null]);
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertOk()->assertSee('Sommerfest');
        $this->get('/')->assertDontSee('Sommerfest');
    }

    public function test_event_form_saves_privacy(): void
    {
        $this->actingAs($this->owner)->put(route('organizer.events.update', [$this->organizer, $this->event]), [
            'title' => 'Fest', 'type' => 'onsite', 'venue_name' => 'Halle', 'starts_at' => '2027-06-12T19:00', 'currency' => 'CHF',
            'is_private' => '1', 'access_key' => 'abcd',
        ])->assertRedirect();
        $this->assertTrue($this->event->fresh()->is_private);
        $this->assertSame('abcd', $this->event->fresh()->access_key);
    }

    public function test_terms_checkbox_is_required_when_links_are_set(): void
    {
        $payload = ['name' => 'Kim', 'email' => 'k@example.ch', 'items' => [$this->event->ticketTypes()->first()->id => 1], 'method' => 'bank'];

        $this->post(route('shop.order', [$this->organizer, $this->event]), $payload)->assertRedirect();
        $this->assertNull(Order::first()->terms_accepted_at);

        $this->organizer->update(['terms_url' => 'https://verein.ch/agb', 'privacy_url' => 'https://verein.ch/datenschutz']);
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertSee('AGB')->assertSee('Datenschutzerklärung');
        $this->post(route('shop.order', [$this->organizer, $this->event]), $payload)->assertSessionHasErrors('accept_terms');
        $this->post(route('shop.order', [$this->organizer, $this->event]), $payload + ['accept_terms' => '1'])->assertRedirect();
        $this->assertNotNull(Order::latest('id')->first()->terms_accepted_at);
    }

    public function test_calendar_export_and_seo_markup(): void
    {
        $res = $this->get(route('shop.calendar', [$this->organizer, $this->event]))->assertOk();
        $this->assertStringContainsString('text/calendar', $res->headers->get('Content-Type'));
        $body = $res->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('SUMMARY:Sommerfest\, gross\; mit Komma', $body);
        $this->assertStringContainsString('LOCATION:Halle\, Weg 1\, Bern', $body);
        $this->assertStringContainsString("\r\n", $body);

        $page = $this->get(route('shop.event', [$this->organizer, $this->event]))->assertOk();
        $page->assertSee('application/ld+json', false)->assertSee('"@type":"Event"', false)->assertSee('og:title', false)->assertSee('calendar.google.com', false);
        $page->assertDontSee('noindex');
    }

    public function test_sponsors_crud_and_display(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner)->post(route('organizer.sponsors.store', [$this->organizer, $this->event]), [
            'sponsor_name' => 'Bäckerei Müller', 'sponsor_url' => 'https://baeckerei.ch', 'sponsor_logo' => UploadedFile::fake()->image('l.png', 200, 100),
        ])->assertRedirect();

        $sponsor = $this->event->sponsors()->firstOrFail();
        Storage::disk('public')->assertExists($sponsor->logo_path);
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertSee('Mit freundlicher Unterstützung')->assertSee('Bäckerei Müller');

        $this->actingAs($this->owner)->delete(route('organizer.sponsors.destroy', [$this->organizer, $this->event, $sponsor]))->assertRedirect();
        $this->assertSame(0, $this->event->sponsors()->count());
        Storage::disk('public')->assertMissing($sponsor->logo_path);
    }

    public function test_ticket_design_settings_apply_to_order_page_and_pdf(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner)->put(route('organizer.settings.update', $this->organizer), [
            'name' => 'Verein A', 'country' => 'CH', 'default_currency' => 'CHF', 'ticket_color' => '#10b981', 'ticket_note' => 'Keine Glasflaschen',
            'terms_url' => 'https://verein.ch/agb', 'reminder_hours' => 24, 'report_frequency' => 'weekly',
            'logo' => UploadedFile::fake()->image('logo.png', 120, 60),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $o = $this->organizer->fresh();
        $this->assertSame('#10b981', $o->ticket_color);
        $this->assertSame(24, $o->reminder_hours);
        $this->assertSame('weekly', $o->report_frequency);
        $this->assertNotNull($o->logo_path);

        $order = $this->paidOrder();
        $this->get(route('order.show', $order->access_token))->assertOk()->assertSee('#10b981', false)->assertSee('Keine Glasflaschen');
        $this->assertStringStartsWith('%PDF', app(\App\Services\TicketPdf::class)->render($order));

        $this->actingAs($this->owner)->put(route('organizer.settings.update', $this->organizer), [
            'name' => 'Verein A', 'country' => 'CH', 'default_currency' => 'CHF', 'remove_logo' => '1', 'ticket_color' => '#zzz',
        ])->assertSessionHasErrors('ticket_color');
    }

    public function test_reminders_are_sent_once_within_the_window(): void
    {
        Mail::fake();
        $this->paidOrder('a@example.ch');
        $this->paidOrder('b@example.ch');

        $this->artisan('tickets:send-reminders')->assertSuccessful();
        Mail::assertNotSent(EventReminderMail::class); // Erinnerung nicht eingerichtet

        $this->organizer->update(['reminder_hours' => 48]);
        $this->artisan('tickets:send-reminders')->assertSuccessful();
        Mail::assertSent(EventReminderMail::class, 2);

        $this->artisan('tickets:send-reminders')->assertSuccessful();
        Mail::assertSent(EventReminderMail::class, 2); // nicht doppelt
        $this->assertNotNull($this->event->fresh()->reminder_sent_at);
    }

    public function test_reminder_not_sent_before_window_opens(): void
    {
        Mail::fake();
        $this->paidOrder();
        $this->event->update(['starts_at' => now()->addDays(10)]);
        $this->organizer->update(['reminder_hours' => 24]);

        $this->artisan('tickets:send-reminders')->assertSuccessful();
        Mail::assertNotSent(EventReminderMail::class);
    }

    public function test_sales_report_goes_to_contact_email_once_per_day(): void
    {
        Mail::fake();
        $this->paidOrder();

        $this->artisan('tickets:send-reports')->assertSuccessful();
        Mail::assertNotSent(SalesReportMail::class);

        $this->organizer->update(['report_frequency' => 'daily']);
        $this->artisan('tickets:send-reports')->assertSuccessful();
        $this->artisan('tickets:send-reports')->assertSuccessful();
        Mail::assertSent(SalesReportMail::class, fn ($m) => $m->hasTo('info@verein.ch') && $m->rows[0]['sold'] === 2 && $m->rows[0]['new'] === 2);
        Mail::assertSent(SalesReportMail::class, 1);
    }

    public function test_check_out_and_re_entry(): void
    {
        $ticket = $this->paidOrder()->tickets()->firstOrFail();
        $svc = app(CheckinService::class);

        $this->assertSame('invalid', $svc->checkCode($this->event, $ticket->code, $this->owner->id, null, 'out')['status']);
        $this->assertSame('ok', $svc->checkCode($this->event, $ticket->code, $this->owner->id)['status']);
        $this->assertSame(1, $svc->stats($this->event)['inside']);

        $this->assertSame('out', $svc->checkCode($this->event, $ticket->code, $this->owner->id, null, 'out')['status']);
        $this->assertSame('duplicate', $svc->checkCode($this->event, $ticket->code, $this->owner->id, null, 'out')['status']);
        $this->assertSame(0, $svc->stats($this->event)['inside']);
        $this->assertSame(1, $svc->stats($this->event)['checked_in']);

        $this->assertSame('Wiedereintritt', $svc->checkCode($this->event, $ticket->code, $this->owner->id)['title']);
        $this->assertSame('duplicate', $svc->checkCode($this->event, $ticket->code, $this->owner->id)['status']);
        $this->assertSame(1, $svc->stats($this->event)['inside']);
    }

    public function test_scan_endpoint_accepts_mode_and_rejects_unknown_modes(): void
    {
        $ticket = $this->paidOrder()->tickets()->firstOrFail();
        $url = route('organizer.scan.check', [$this->organizer, $this->event]);

        $this->actingAs($this->owner)->postJson($url, ['code' => $ticket->code])->assertJsonPath('status', 'ok');
        $this->actingAs($this->owner)->postJson($url, ['code' => $ticket->code, 'mode' => 'out'])->assertJsonPath('status', 'out')->assertJsonPath('stats.inside', 0);
        $this->actingAs($this->owner)->postJson($url, ['code' => $ticket->code, 'mode' => 'bogus'])->assertStatus(422);
    }

    public function test_customers_list_and_export(): void
    {
        $this->paidOrder('a@example.ch');
        $this->paidOrder('A@example.ch');
        $this->paidOrder('b@example.ch');

        $this->actingAs($this->owner)->get(route('organizer.customers.index', $this->organizer))->assertOk()->assertSee('a@example.ch')->assertSee('b@example.ch');
        $this->actingAs($this->owner)->get(route('organizer.customers.index', [$this->organizer, 'q' => 'b@']))->assertOk()->assertDontSee('a@example.ch');

        $csv = $this->actingAs($this->owner)->get(route('organizer.customers.export', $this->organizer))->assertOk()->streamedContent();
        $this->assertStringContainsString('a@example.ch;2;4;80.00', $csv);
        $this->assertStringContainsString('b@example.ch;1;2;40.00', $csv);
    }
}
