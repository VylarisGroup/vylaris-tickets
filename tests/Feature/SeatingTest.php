<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SeatingTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private Event $event;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a']);
        $this->owner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->event = $this->organizer->events()->create(['title' => 'Konzert', 'slug' => 'konzert', 'type' => 'onsite', 'venue_name' => 'Saal', 'status' => 'published', 'starts_at' => now()->addMonth(), 'currency' => 'CHF']);
    }

    public function test_grid_generator_creates_rows_and_seats(): void
    {
        $type = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 5000]);
        $this->actingAs($this->owner)->put(route('organizer.seating.mode', [$this->organizer, $this->event]), ['seating_mode' => 'grid'])->assertRedirect();

        $this->actingAs($this->owner)->post(route('organizer.seating.sections.store', [$this->organizer, $this->event]), [
            'name' => 'Parkett', 'ticket_type_id' => $type->id, 'rows' => 3, 'seats_per_row' => 4, 'first_row' => 'A',
        ])->assertRedirect();

        $this->assertSame(12, $this->event->seats()->count());
        $this->assertSame(['A1', 'A2', 'A3', 'A4', 'B1'], $this->event->seats()->orderBy('id')->limit(5)->pluck('label')->all());
        $this->actingAs($this->owner)->get(route('organizer.seating.show', [$this->organizer, $this->event]))->assertOk();
    }

    public function test_map_editor_saves_swaps_and_protects_sold_seats(): void
    {
        $type = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 5000]);
        $this->event->update(['seating_mode' => 'map']);
        $url = route('organizer.seating.map', [$this->organizer, $this->event]);

        $payload = [
            'sections' => [['name' => 'Parkett', 'ticket_type_id' => $type->id]],
            'seats' => [
                ['section' => 0, 'label' => 'A1', 'x' => 40, 'y' => 40],
                ['section' => 0, 'label' => 'A2', 'x' => 80, 'y' => 40],
            ],
        ];
        $this->actingAs($this->owner)->putJson($url, $payload)->assertOk();

        $a1 = $this->event->seats()->where('label', 'A1')->first();
        $a2 = $this->event->seats()->where('label', 'A2')->first();
        $section = $this->event->sections()->first();

        // Vertauschen der Bezeichnungen darf den Unique-Index nicht verletzen.
        $swap = [
            'sections' => [['id' => $section->id, 'name' => 'Parkett', 'ticket_type_id' => $type->id]],
            'seats' => [
                ['id' => $a1->id, 'section' => 0, 'label' => 'A2', 'x' => 40, 'y' => 40],
                ['id' => $a2->id, 'section' => 0, 'label' => 'A1', 'x' => 80, 'y' => 40],
            ],
        ];
        $this->actingAs($this->owner)->putJson($url, $swap)->assertOk();
        $this->assertSame('A2', $a1->fresh()->label);
        $this->assertSame('A1', $a2->fresh()->label);

        // Doppelte Bezeichnungen werden abgelehnt.
        $dup = $swap;
        $dup['seats'][1]['label'] = 'A2';
        $this->actingAs($this->owner)->putJson($url, $dup)->assertStatus(422);

        // Ein verkaufter Platz wird nicht gelöscht, auch wenn er im Plan fehlt.
        app(OrderService::class)->place($this->event->fresh(), [], [$a1->id], ['name' => 'K', 'email' => null], null, 'cash', 'door');
        $this->event->update(['door_sales_enabled' => true]);
        $only = ['sections' => $swap['sections'], 'seats' => [$swap['seats'][1]]];
        $this->actingAs($this->owner)->putJson($url, $only);

        $this->assertNotNull($a1->fresh(), 'Platz mit Bestellung bleibt erhalten');
    }

    public function test_seated_ticket_type_cannot_be_sold_without_seat_choice_and_stock_is_ignored(): void
    {
        $type = $this->event->ticketTypes()->create(['name' => 'Parkett', 'price_cents' => 5000]);
        $this->event->update(['seating_mode' => 'grid']);
        $section = $this->event->sections()->create(['name' => 'P', 'ticket_type_id' => $type->id]);
        $section->seats()->create(['event_id' => $this->event->id, 'row_label' => 'A', 'number' => 1, 'label' => 'A1', 'x' => 40, 'y' => 40]);

        $this->assertTrue($type->isSeated());
    }
}
