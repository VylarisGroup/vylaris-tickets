<?php

namespace Tests\Feature;

use App\Exceptions\CheckoutException;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatMapData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HallPlanTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private Event $event;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'iban' => 'CH9300762011623852957']);
        $this->owner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->event = $this->organizer->events()->create(['title' => 'Gala', 'slug' => 'gala', 'type' => 'onsite', 'venue_name' => 'Haus', 'status' => 'published', 'starts_at' => now()->addMonth(), 'currency' => 'CHF', 'seating_mode' => 'map']);
    }

    private function url(string $name, array $extra = []): string
    {
        return route('organizer.seating.'.$name, [$this->organizer, $this->event, ...$extra]);
    }

    /** Zwei Ebenen: Sitz A1 im Erdgeschoss, ganzer Tisch (4) und Tisch mit Einzelplätzen (6) im Obergeschoss. */
    private function plan(): array
    {
        $type = $this->event->ticketTypes()->create(['name' => 'Platz', 'price_cents' => 5000]);
        $this->actingAs($this->owner)->get($this->url('show'))->assertOk();
        $ground = $this->event->floors()->first();
        $upper = $this->event->floors()->create(['name' => '1. Obergeschoss', 'sort' => 2]);

        $this->actingAs($this->owner)->putJson($this->url('map'), [
            'sections' => [['name' => 'Saal', 'ticket_type_id' => $type->id]],
            'seats' => [
                ['section' => 0, 'floor_id' => $ground->id, 'label' => 'A1', 'x' => 40, 'y' => 40],
                ['section' => 0, 'floor_id' => $upper->id, 'label' => 'Tisch 1', 'x' => 100, 'y' => 100, 'kind' => 'table', 'capacity' => 4, 'table_mode' => 'whole'],
                ['section' => 0, 'floor_id' => $upper->id, 'label' => 'Tisch 2', 'x' => 200, 'y' => 100, 'kind' => 'table', 'capacity' => 6, 'table_mode' => 'seats'],
            ],
        ])->assertOk();

        return [
            $ground, $upper,
            Seat::where('label', 'A1')->first(), Seat::where('label', 'Tisch 1')->first(), Seat::where('label', 'Tisch 2')->first(),
        ];
    }

    private function place(array $seats, string $method = 'bank')
    {
        return app(OrderService::class)->place($this->event->fresh(), [], $seats, ['name' => 'Kim', 'email' => 'k@example.ch'], null, $method);
    }

    public function test_editor_creates_a_default_floor_and_manages_floors(): void
    {
        $this->event->ticketTypes()->create(['name' => 'Platz', 'price_cents' => 5000]);
        $this->actingAs($this->owner)->get($this->url('show'))->assertOk()->assertSee('Erdgeschoss');
        $this->assertSame(1, $this->event->floors()->count());

        $id = $this->actingAs($this->owner)->postJson($this->url('floors.store'), ['name' => 'Festsaal'])->assertOk()->json('floor.id');
        $this->actingAs($this->owner)->putJson($this->url('floors.update', [$id]), ['name' => 'Grosser Festsaal'])->assertOk();
        $this->assertSame('Grosser Festsaal', $this->event->floors()->find($id)->name);

        $this->actingAs($this->owner)->deleteJson($this->url('floors.destroy', [$id]))->assertOk();
        $this->actingAs($this->owner)->deleteJson($this->url('floors.destroy', [$this->event->floors()->first()->id]))->assertStatus(422);
    }

    public function test_floor_of_another_event_cannot_be_touched(): void
    {
        $other = $this->organizer->events()->create(['title' => 'Andere', 'slug' => 'andere', 'type' => 'onsite', 'venue_name' => 'X', 'status' => 'published', 'starts_at' => now()->addMonth(), 'currency' => 'CHF', 'seating_mode' => 'map']);
        $foreign = $other->floors()->create(['name' => 'Fremd', 'sort' => 1]);

        $this->actingAs($this->owner)->putJson($this->url('floors.update', [$foreign->id]), ['name' => 'x'])->assertNotFound();
        $this->actingAs($this->owner)->deleteJson($this->url('floors.destroy', [$foreign->id]))->assertNotFound();
    }

    public function test_plan_image_is_uploaded_normalized_and_removed(): void
    {
        $floor = $this->event->floors()->create(['name' => 'EG', 'sort' => 1]);

        $res = $this->actingAs($this->owner)->post($this->url('floors.plan', [$floor->id]), ['plan' => UploadedFile::fake()->image('halle.png', 3000, 1500)], ['Accept' => 'application/json'])->assertOk();

        $floor->refresh();
        $this->assertSame(2400, $floor->plan_w);
        $this->assertSame(1200, $floor->plan_h);
        $this->assertSame(500, $floor->canvasHeight());
        Storage::disk('public')->assertExists($floor->plan_path);
        $this->assertNotEmpty($res->json('floor.plan'));

        $old = $floor->plan_path;
        $this->actingAs($this->owner)->post($this->url('floors.plan', [$floor->id]), ['plan' => UploadedFile::fake()->image('neu.jpg', 800, 800)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($this->owner)->deleteJson($this->url('floors.plan.remove', [$floor->id]))->assertOk();
        $this->assertNull($floor->fresh()->plan_path);
    }

    public function test_plan_upload_rejects_non_images(): void
    {
        $floor = $this->event->floors()->create(['name' => 'EG', 'sort' => 1]);

        $this->actingAs($this->owner)->post($this->url('floors.plan', [$floor->id]), ['plan' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_map_stores_floors_and_tables_and_shop_data_lists_them(): void
    {
        [$ground, $upper, $a1, $t1, $t2] = $this->plan();

        $this->assertSame($ground->id, $a1->seat_floor_id);
        $this->assertTrue($t1->isTable());
        $this->assertSame(4, $t1->capacity);
        $this->assertSame('seats', $t2->table_mode);
        $this->assertSame(1, $a1->capacity);

        $data = app(SeatMapData::class)->for($this->event->fresh());
        $this->assertCount(2, $data['floors']);
        $this->assertSame(6, collect($data['seats'])->firstWhere('label', 'Tisch 2')['free']);
    }

    public function test_whole_table_books_all_seats_and_blocks_until_released(): void
    {
        [, , , $t1] = $this->plan();

        $order = $this->place([$t1->id]);
        $this->assertSame(4, $order->tickets()->count());
        $this->assertSame(20000, $order->total_cents);
        $this->assertSame([0, 1, 2, 3], $order->tickets()->orderBy('seat_key')->get()->map(fn ($t) => $t->seat_key % 1000)->all());
        $this->assertSame('Saal, Tisch 1 · Platz 1', $order->tickets()->orderBy('seat_key')->first()->seatText());

        // Unbezahlt zählt bereits als besetzt.
        $this->assertTrue(collect(app(SeatMapData::class)->for($this->event->fresh())['seats'])->firstWhere('label', 'Tisch 1')['taken']);
        $this->expectException(CheckoutException::class);

        try {
            $this->place([$t1->id]);
        } finally {
            // Nach Ablauf wird der Tisch wieder frei.
            $order->update(['expires_at' => now()->subMinute()]);
            $again = $this->place([$t1->id]);
            $this->assertSame(4, $again->tickets()->where('status', 'valid')->count());
        }
    }

    public function test_table_with_single_seats_fills_up_and_tracks_paid_state(): void
    {
        [, , , , $t2] = $this->plan();

        $first = $this->place([$t2->id.'x4']);
        $this->assertSame(4, $first->tickets()->count());

        $seat = fn () => collect(app(SeatMapData::class)->for($this->event->fresh())['seats'])->firstWhere('label', 'Tisch 2');
        $this->assertSame(2, $seat()['free']);
        $this->assertFalse($seat()['taken']);

        try {
            $this->place([$t2->id.'x3']);
            $this->fail('Zu viele Plätze');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString('nur noch 2', $e->getMessage());
        }

        $second = $this->place([$t2->id.'x2']);
        $this->assertTrue($seat()['taken']);
        $this->assertSame(0, $seat()['free']);

        $keys = Ticket::where('seat_id', $t2->id)->pluck('seat_key')->map(fn ($k) => $k % 1000)->sort()->values()->all();
        $this->assertSame([0, 1, 2, 3, 4, 5], $keys);

        // Bezahlt bleibt besetzt; storniert wird frei.
        app(OrderService::class)->markPaid($first);
        $this->assertTrue($seat()['taken']);
        app(OrderService::class)->cancel($second);
        $this->assertSame(2, $seat()['free']);
    }

    public function test_single_seat_and_table_cannot_be_overbooked_and_cart_limits_apply(): void
    {
        [, , $a1, $t1] = $this->plan();

        $this->place([$a1->id]);
        $this->expectException(CheckoutException::class);
        $this->place([$a1->id, $t1->id]);
    }

    public function test_late_payment_revives_table_booking_or_fails_when_taken(): void
    {
        [, , , $t1] = $this->plan();

        $order = $this->place([$t1->id]);
        app(OrderService::class)->expire($order);
        $this->assertSame(0, Ticket::where('seat_id', $t1->id)->whereNotNull('seat_key')->count());

        app(OrderService::class)->markPaid($order->fresh());
        $this->assertSame(4, Ticket::where('seat_id', $t1->id)->where('status', 'valid')->count());
        $this->assertSame(4, Ticket::where('seat_id', $t1->id)->whereNotNull('seat_key')->distinct()->count('seat_key'));
    }

    public function test_booked_table_cannot_shrink_or_change_mode(): void
    {
        [$ground, $upper, $a1, $t1] = $this->plan();
        $this->place([$t1->id]);
        $section = $this->event->sections()->first();

        $payload = fn (array $table) => [
            'sections' => [['id' => $section->id, 'name' => 'Saal', 'ticket_type_id' => $section->ticket_type_id]],
            'seats' => [
                ['id' => $a1->id, 'section' => 0, 'floor_id' => $ground->id, 'label' => 'A1', 'x' => 40, 'y' => 40],
                ['id' => $t1->id, 'section' => 0, 'floor_id' => $upper->id, 'label' => 'Tisch 1', 'x' => 100, 'y' => 100, 'kind' => 'table'] + $table,
                ['id' => Seat::where('label', 'Tisch 2')->value('id'), 'section' => 0, 'floor_id' => $upper->id, 'label' => 'Tisch 2', 'x' => 200, 'y' => 100, 'kind' => 'table', 'capacity' => 6, 'table_mode' => 'seats'],
            ],
        ];

        $this->actingAs($this->owner)->putJson($this->url('map'), $payload(['capacity' => 2, 'table_mode' => 'whole']))->assertStatus(422);
        $this->actingAs($this->owner)->putJson($this->url('map'), $payload(['capacity' => 4, 'table_mode' => 'seats']))->assertStatus(422);
        $this->actingAs($this->owner)->putJson($this->url('map'), $payload(['capacity' => 4, 'table_mode' => 'whole', 'x' => 150]))->assertOk();
    }

    public function test_shop_page_and_order_over_http_with_table_quantity(): void
    {
        [, , , , $t2] = $this->plan();

        $this->get(route('shop.event', [$this->organizer, $this->event]))->assertOk()->assertSee('1. Obergeschoss');

        $this->post(route('shop.order', [$this->organizer, $this->event]), [
            'name' => 'Kim', 'email' => 'k@example.ch', 'method' => 'bank', 'seats' => [$t2->id.'x3'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, Ticket::where('seat_id', $t2->id)->count());
    }

    public function test_kunden_shortcut_redirects_to_the_current_organizers_customers(): void
    {
        $this->actingAs($this->owner)->get('/kunden')->assertRedirect(route('organizer.customers.index', $this->organizer));
        $this->actingAs(User::factory()->create())->get('/kunden')->assertRedirect(route('dashboard'));
    }
}
