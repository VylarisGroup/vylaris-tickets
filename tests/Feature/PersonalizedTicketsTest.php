<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TicketPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalizedTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private Event $event;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'contact_email' => 'i@v.ch']);
        $this->owner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->event = $this->organizer->events()->create([
            'title' => 'Fest', 'slug' => 'fest', 'type' => 'onsite', 'venue_name' => 'H', 'starts_at' => now()->addDay(), 'currency' => 'CHF', 'status' => 'published',
            'personalize_tickets' => true, 'attendee_fields' => ['birthdate', 'city', 'email'],
        ]);
        $this->event->ticketTypes()->create(['name' => 'Standard', 'price_cents' => 0, 'max_per_order' => 10, 'sort' => 1, 'is_active' => true]);
        $this->event->ticketTypes()->create(['name' => 'VIP', 'price_cents' => 0, 'max_per_order' => 10, 'sort' => 2, 'is_active' => true]);
    }

    private function order(array $holders, ?array $items = null)
    {
        $std = $this->event->ticketTypes()->where('name', 'Standard')->first();
        $vip = $this->event->ticketTypes()->where('name', 'VIP')->first();

        return $this->post(route('shop.order', [$this->organizer, $this->event]), [
            'name' => 'Kim Käufer', 'email' => 'kim@example.ch', 'method' => 'free',
            'items' => $items ?? [$std->id => 2, $vip->id => 1],
            'holders' => $holders,
        ]);
    }

    private function holders(): array
    {
        $std = $this->event->ticketTypes()->where('name', 'Standard')->first()->id;
        $vip = $this->event->ticketTypes()->where('name', 'VIP')->first()->id;

        return [
            "t{$std}-0" => ['name' => 'Anna Muster', 'birthdate' => '1990-03-12', 'city' => 'Bern', 'email' => 'anna@example.ch'],
            "t{$std}-1" => ['name' => 'Ben Beispiel', 'birthdate' => '12.04.1985', 'city' => 'Zürich', 'email' => 'ben@example.ch'],
            "t{$vip}-0" => ['name' => 'Cleo Gast', 'birthdate' => '2001-12-31', 'city' => 'Basel', 'email' => 'cleo@example.ch'],
        ];
    }

    public function test_each_ticket_gets_its_own_person_and_shows_it(): void
    {
        $this->order($this->holders())->assertRedirect()->assertSessionHasNoErrors();

        $tickets = Ticket::with('ticketType')->get()->keyBy('holder_name');
        $this->assertCount(3, $tickets);
        $this->assertSame('1990-03-12', $tickets['Anna Muster']->holder_birthdate->toDateString());
        $this->assertSame('1985-04-12', $tickets['Ben Beispiel']->holder_birthdate->toDateString()); // Handeingabe d.m.Y
        $this->assertSame('VIP', $tickets['Cleo Gast']->ticketType->name);
        $this->assertSame('Basel', $tickets['Cleo Gast']->holder_city);
        $this->assertSame('cleo@example.ch', $tickets['Cleo Gast']->holder_email);

        // Käufer bleibt Besteller, steht aber nicht als Teilnehmer auf den Tickets.
        $order = $tickets['Anna Muster']->order;
        $this->assertSame('Kim Käufer', $order->customer_name);

        $this->get(route('order.show', $order->access_token))->assertOk()->assertSee('Anna Muster')->assertSee('12.03.1990')->assertSee('Basel')->assertDontSee('anna@example.ch');
        $this->assertStringStartsWith('%PDF', app(TicketPdf::class)->render($order));
        $this->assertSame(['Geburtsdatum' => '12.03.1990', 'Wohnort' => 'Bern'], $tickets['Anna Muster']->holderDetails());
    }

    public function test_missing_or_invalid_details_are_rejected_without_creating_an_order(): void
    {
        $h = $this->holders();
        $first = array_key_first($h);

        $missing = $h;
        unset($missing[$first]);
        $this->order($missing)->assertSessionHasErrors('checkout');

        $noBirth = $h;
        $noBirth[$first]['birthdate'] = '';
        $this->order($noBirth)->assertSessionHasErrors('checkout');

        $future = $h;
        $future[$first]['birthdate'] = now()->addYear()->toDateString();
        $this->order($future)->assertSessionHasErrors('checkout');

        $badMail = $h;
        $badMail[$first]['email'] = 'keine-mail';
        $this->order($badMail)->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_events_without_personalization_work_as_before(): void
    {
        $this->event->update(['personalize_tickets' => false]);
        $this->order([])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['Kim Käufer'], Ticket::pluck('holder_name')->unique()->values()->all());
    }

    public function test_door_sales_do_not_require_details(): void
    {
        $order = app(OrderService::class)->place($this->event->fresh(), [$this->event->ticketTypes()->first()->id => 1], [], ['name' => '', 'email' => null], null, 'cash', 'door', $this->owner);
        $this->assertSame('Abendkasse', $order->tickets()->first()->holder_name);
    }

    public function test_organizer_can_enable_it_and_list_and_export_show_the_data(): void
    {
        $this->event->update(['personalize_tickets' => false, 'attendee_fields' => null]);

        $payload = [
            'title' => 'Fest', 'type' => 'onsite', 'venue_name' => 'H', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'currency' => 'CHF',
            'personalize_tickets' => '1', 'attendee_fields' => ['birthdate', 'phone', 'unbekannt'],
        ];
        $this->actingAs($this->owner)->put(route('organizer.events.update', [$this->organizer, $this->event]), $payload)->assertSessionHasErrors('attendee_fields.2');

        $payload['attendee_fields'] = ['birthdate', 'phone'];
        $this->actingAs($this->owner)->put(route('organizer.events.update', [$this->organizer, $this->event]), $payload)->assertSessionHasNoErrors();
        $event = $this->event->fresh();
        $this->assertTrue($event->personalize_tickets);
        $this->assertSame(['birthdate', 'phone'], $event->attendeeFields());

        $this->event->update(['attendee_fields' => ['birthdate', 'city', 'email']]);
        $this->order($this->holders())->assertRedirect();

        $this->actingAs($this->owner)->get(route('organizer.attendees.index', [$this->organizer, $this->event]))->assertOk()->assertSee('Anna Muster')->assertSee('geb. 12.03.1990');
        $csv = $this->actingAs($this->owner)->get(route('organizer.attendees.export', [$this->organizer, $this->event]))->streamedContent();
        $this->assertStringContainsString('Geburtsdatum', $csv);
        $this->assertStringContainsString("\"Anna Muster\";12.03.1990;Bern;anna@example.ch", $csv);
    }

    public function test_scanner_shows_birthdate_for_age_checks(): void
    {
        $this->order($this->holders());
        $ticket = Ticket::where('holder_name', 'Anna Muster')->first();

        $this->actingAs($this->owner)->postJson(route('organizer.scan.check', [$this->organizer, $this->event]), ['code' => $ticket->code])
            ->assertOk()->assertJsonPath('ticket.name', 'Anna Muster')->assertJsonPath('ticket.birthdate', '12.03.1990');
    }

    public function test_shop_page_renders_the_person_form(): void
    {
        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertOk()->assertSee('Angaben zu den Personen')->assertSee('Geburtsdatum')->assertSee('checkAll($event)', false)->assertSee('data-slot', false)->assertSee('Zurück');
    }
}
