<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a']);
        $this->owner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
    }

    private function eventPayload(array $over = []): array
    {
        return array_merge([
            'title' => 'Sommerfest', 'type' => 'onsite', 'venue_name' => 'Mehrzweckhalle',
            'starts_at' => '2027-06-12T19:00', 'currency' => 'CHF', 'door_sales_enabled' => 1,
        ], $over);
    }

    private function makeEvent(array $over = []): Event
    {
        return $this->organizer->events()->create(array_merge([
            'title' => 'Sommerfest', 'slug' => 'sommerfest', 'type' => 'onsite', 'venue_name' => 'Halle',
            'starts_at' => now()->addMonth(), 'currency' => 'CHF',
        ], $over));
    }

    public function test_owner_creates_onsite_event_as_draft_with_unique_slug(): void
    {
        $this->actingAs($this->owner)->post(route('organizer.events.store', $this->organizer), $this->eventPayload())->assertRedirect();
        $this->actingAs($this->owner)->post(route('organizer.events.store', $this->organizer), $this->eventPayload());

        $this->assertSame(['sommerfest', 'sommerfest-2'], $this->organizer->events()->orderBy('id')->pluck('slug')->all());
        $this->assertSame(EventStatus::Draft, $this->organizer->events()->first()->status);
    }

    public function test_digital_event_needs_url_but_no_venue(): void
    {
        $payload = $this->eventPayload(['type' => 'digital', 'venue_name' => '']);

        $this->actingAs($this->owner)->post(route('organizer.events.store', $this->organizer), $payload)->assertSessionHasErrors('online_url');

        $this->actingAs($this->owner)->post(route('organizer.events.store', $this->organizer), $payload + ['online_url' => 'https://meet.example.ch/abc'])->assertSessionDoesntHaveErrors();

        $event = $this->organizer->events()->first();
        $this->assertSame('https://meet.example.ch/abc', $event->online_url);
        $this->assertFalse($event->door_sales_enabled);
    }

    public function test_onsite_event_discards_online_fields(): void
    {
        $this->actingAs($this->owner)->post(route('organizer.events.store', $this->organizer), $this->eventPayload(['online_url' => 'https://x.example.ch']));

        $this->assertNull($this->organizer->events()->first()->online_url);
    }

    public function test_online_link_is_hidden_in_serialization(): void
    {
        $event = $this->makeEvent(['type' => 'digital', 'online_url' => 'https://geheim.example.ch']);

        $this->assertArrayNotHasKey('online_url', $event->toArray());
    }

    public function test_event_limit_of_reseller_is_enforced(): void
    {
        $reseller = Reseller::create(['name' => 'R', 'slug' => 'r', 'max_events_per_organizer' => 1]);
        $this->organizer->update(['reseller_id' => $reseller->id]);
        $this->makeEvent();

        $this->actingAs($this->owner)->post(route('organizer.events.store', $this->organizer), $this->eventPayload(['title' => 'Zweites']))
            ->assertSessionHasErrors('title');
    }

    public function test_publishing_requires_an_active_ticket_type(): void
    {
        $event = $this->makeEvent();
        $url = route('organizer.events.status', [$this->organizer, $event]);

        $this->actingAs($this->owner)->patch($url, ['status' => 'published'])->assertSessionHasErrors('status');

        $event->ticketTypes()->create(['name' => 'Normal', 'price_cents' => 2500]);
        $this->actingAs($this->owner)->patch($url, ['status' => 'published'])->assertSessionDoesntHaveErrors();

        $this->assertSame(EventStatus::Published, $event->fresh()->status);
    }

    public function test_ticket_price_accepts_comma_and_stores_cents(): void
    {
        $event = $this->makeEvent();

        $this->actingAs($this->owner)->post(route('organizer.tickets.store', [$this->organizer, $event]), [
            'name' => 'Ermässigt', 'price' => '12,50', 'quota' => 100, 'max_per_order' => 6,
        ])->assertSessionDoesntHaveErrors();

        $type = $event->ticketTypes()->first();
        $this->assertSame(1250, $type->price_cents);
        $this->assertSame('CHF 12.50', $type->price());

        $this->actingAs($this->owner)->post(route('organizer.tickets.store', [$this->organizer, $event]), [
            'name' => 'Falsch', 'price' => 'abc', 'max_per_order' => 1,
        ])->assertSessionHasErrors('price_cents');
    }

    public function test_free_ticket_is_allowed(): void
    {
        $event = $this->makeEvent();

        $this->actingAs($this->owner)->post(route('organizer.tickets.store', [$this->organizer, $event]), [
            'name' => 'Gästeliste', 'price' => '0', 'max_per_order' => 2,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('Gratis', $event->ticketTypes()->first()->price());
    }

    public function test_events_of_other_organizers_are_not_reachable(): void
    {
        $other = Organizer::create(['name' => 'Verein B', 'slug' => 'verein-b']);
        $foreign = $other->events()->create(['title' => 'Fremd', 'slug' => 'fremd', 'type' => 'onsite', 'venue_name' => 'X', 'starts_at' => now()->addDay(), 'currency' => 'CHF']);

        $this->actingAs($this->owner)->get(route('organizer.events.show', [$this->organizer, $foreign]))->assertNotFound();
    }

    public function test_cashier_cannot_manage_events(): void
    {
        $cashier = User::factory()->create();
        $this->organizer->users()->attach($cashier, ['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('organizer.events.index', $this->organizer))->assertForbidden();
    }

    public function test_voucher_creation_validates_and_stores_values(): void
    {
        $url = route('organizer.vouchers.store', $this->organizer);

        $this->actingAs($this->owner)->post($url, ['code' => 'fruehling25', 'type' => 'percent', 'value' => '15'])->assertSessionDoesntHaveErrors();
        $this->actingAs($this->owner)->post($url, ['code' => 'FIX5', 'type' => 'fixed', 'value' => '5,50'])->assertSessionDoesntHaveErrors();
        $this->actingAs($this->owner)->post($url, ['code' => 'ZUVIEL', 'type' => 'percent', 'value' => '150'])->assertSessionHasErrors('value');
        $this->actingAs($this->owner)->post($url, ['code' => 'FRUEHLING25', 'type' => 'percent', 'value' => '5'])->assertSessionHasErrors('code');

        $this->assertSame(15, $this->organizer->vouchers()->where('code', 'FRUEHLING25')->value('value'));
        $this->assertSame(550, $this->organizer->vouchers()->where('code', 'FIX5')->value('value'));
    }

    public function test_voucher_cannot_target_event_of_other_organizer(): void
    {
        $other = Organizer::create(['name' => 'Verein B', 'slug' => 'verein-b']);
        $foreign = $other->events()->create(['title' => 'Fremd', 'slug' => 'fremd', 'type' => 'onsite', 'venue_name' => 'X', 'starts_at' => now()->addDay(), 'currency' => 'CHF']);

        $this->actingAs($this->owner)->post(route('organizer.vouchers.store', $this->organizer), [
            'code' => 'X1', 'type' => 'percent', 'value' => '10', 'event_id' => $foreign->id,
        ])->assertSessionHasErrors('event_id');
    }

    public function test_all_management_pages_render(): void
    {
        $event = $this->makeEvent();
        $type = $event->ticketTypes()->create(['name' => 'Normal', 'price_cents' => 2500, 'quota' => 50]);
        $this->organizer->vouchers()->create(['code' => 'TEST10', 'type' => 'percent', 'value' => 10]);

        $this->actingAs($this->owner);

        foreach ([
            route('organizer.dashboard', $this->organizer),
            route('organizer.events.index', $this->organizer),
            route('organizer.events.create', $this->organizer),
            route('organizer.events.show', [$this->organizer, $event]),
            route('organizer.events.edit', [$this->organizer, $event]),
            route('organizer.vouchers.index', $this->organizer),
            route('organizer.members.index', $this->organizer),
            route('organizer.settings.edit', $this->organizer),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs(User::factory()->create(['is_platform_admin' => true]));
        foreach ([route('admin.dashboard'), route('admin.resellers.index'), route('admin.resellers.create'), route('admin.organizers.index'), route('dashboard', ['alle' => 1])] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
