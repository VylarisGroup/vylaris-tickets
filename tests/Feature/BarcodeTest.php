<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Code128;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_code128_encodes_known_value_with_checksum(): void
    {
        // Prüfwert: (104 + 48·1 + 42·2 + 42·3 + 17·4 + 18·5 + 19·6 + 35·7) mod 103 = 55; Gegenprobe mit der Bibliothek python-barcode.
        $modules = Code128::modules('PJJ123C');

        $this->assertStringStartsWith('11010010000', $modules); // Start B
        $this->assertStringEndsWith('1100011101011', $modules); // Stopp
        $this->assertSame(11 * (1 + 7 + 1) + 13, strlen($modules));
        $this->assertSame('11101000110', substr($modules, -24, 11)); // Prüfzeichen 55
    }

    public function test_only_printable_ascii_is_supported(): void
    {
        $this->assertTrue(Code128::supports('ABC123'));
        $this->assertFalse(Code128::supports('Grüsse'));
        $this->assertFalse(Code128::supports(''));
        $this->expectException(\InvalidArgumentException::class);
        Code128::modules("a\tb");
    }

    public function test_png_is_a_valid_data_uri(): void
    {
        $uri = Code128::png('ABCDEFGHJKLMNPQRSTUV');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $info = getimagesizefromstring(base64_decode(substr($uri, 22)));
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
        $this->assertGreaterThan(500, $info[0]);
    }

    public function test_scan_page_announces_hand_scanner_support_and_ticket_has_barcode(): void
    {
        $organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'contact_email' => 'i@v.ch']);
        $user = User::factory()->create();
        $organizer->users()->attach($user, ['role' => 'owner']);
        $event = $organizer->events()->create(['title' => 'Fest', 'slug' => 'fest', 'type' => 'onsite', 'venue_name' => 'H', 'starts_at' => now()->addDay(), 'currency' => 'CHF', 'status' => 'published']);
        $event->ticketTypes()->create(['name' => 'Std', 'price_cents' => 0, 'max_per_order' => 10, 'sort' => 1, 'is_active' => true]);
        $order = app(OrderService::class)->markPaid(app(OrderService::class)->place($event->fresh(), [$event->ticketTypes()->first()->id => 1], [], ['name' => 'Kim', 'email' => 'k@e.ch'], null, 'bank'));

        $this->actingAs($user)->get(route('organizer.scan.show', [$organizer, $event]))->assertOk()->assertSee('Handscanner bereit');
        $this->get(route('order.show', $order->access_token))->assertOk()->assertSee('alt="Strichcode"', false);
        $this->assertStringStartsWith('%PDF', app(\App\Services\TicketPdf::class)->render($order));
    }
}
