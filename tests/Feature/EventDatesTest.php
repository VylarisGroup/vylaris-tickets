<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_prefills_the_date_picker_and_wizard_renders_it(): void
    {
        $organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'contact_email' => 'i@v.ch']);
        $user = User::factory()->create();
        $organizer->users()->attach($user, ['role' => 'owner']);
        $event = $organizer->events()->create([
            'title' => 'Fest', 'slug' => 'fest', 'type' => 'onsite', 'venue_name' => 'H', 'currency' => 'CHF', 'status' => 'draft',
            'starts_at' => '2030-05-17 20:00:00', 'ends_at' => '2030-05-18 02:00:00', 'doors_open_at' => '2030-05-17 19:30:00',
        ]);

        $this->actingAs($user)->get(route('organizer.events.edit', [$organizer, $event]))
            ->assertOk()->assertSee('eventDates', false)->assertSee('2030-05-17T20:00', false)->assertSee('2030-05-18T02:00', false)->assertSee('2030-05-17T19:30', false)
            ->assertSee('Mehrtägig')->assertSee('Min. vorher', false)->assertSee('Ende 1 Std. vor Beginn');

        $this->actingAs($user)->get(route('organizer.wizard', $organizer))->assertOk()->assertSee('eventDates', false);
    }

    public function test_dates_from_the_picker_are_saved_and_end_before_start_is_rejected(): void
    {
        $organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'contact_email' => 'i@v.ch']);
        $user = User::factory()->create();
        $organizer->users()->attach($user, ['role' => 'owner']);
        $base = ['title' => 'Fest', 'type' => 'onsite', 'venue_name' => 'H', 'currency' => 'CHF', 'starts_at' => '2030-05-17T22:00', 'ends_at' => '2030-05-18T02:00', 'doors_open_at' => '2030-05-17T21:30'];

        $this->actingAs($user)->post(route('organizer.events.store', $organizer), $base)->assertSessionHasNoErrors();
        $event = $organizer->events()->first();
        $this->assertSame('2030-05-18 02:00', $event->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2030-05-17 21:30', $event->doors_open_at->format('Y-m-d H:i'));

        $this->actingAs($user)->post(route('organizer.events.store', $organizer), ['ends_at' => '2030-05-17T20:00'] + $base)->assertSessionHasErrors('ends_at');
        $this->actingAs($user)->post(route('organizer.events.store', $organizer), ['doors_open_at' => '2030-05-17T23:00'] + $base)->assertSessionHasErrors('doors_open_at');
    }
}
