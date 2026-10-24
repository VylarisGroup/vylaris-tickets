<?php

namespace Tests\Feature;

use App\Models\Reseller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_installable_with_separate_any_and_maskable_icons(): void
    {
        $json = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();

        $this->assertSame('standalone', $json['display']);
        $this->assertSame('/', $json['scope']);
        $this->assertSame('Vylaris Tickets', $json['name']);
        $this->assertSame('#1867f8', $json['theme_color']);

        $purposes = collect($json['icons'])->groupBy('purpose')->map->pluck('sizes')->map->all();
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], $purposes['any']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], $purposes['maskable']);
        $this->assertNotContains('any maskable', collect($json['icons'])->pluck('purpose')->all());
    }

    public function test_manifest_follows_reseller_branding(): void
    {
        Reseller::create(['name' => 'Eventhaus Bern', 'slug' => 'eventhaus', 'domain' => 'tickets.eventhaus.ch', 'brand_color' => '#e11d48']);

        $json = $this->get('http://tickets.eventhaus.ch/manifest.webmanifest')->assertOk()->json();

        $this->assertSame('Eventhaus Bern', $json['name']);
        $this->assertSame('#e11d48', $json['theme_color']);
        $this->assertStringContainsString('c=e11d48', $json['icons'][0]['src']);
    }

    public function test_icons_are_valid_pngs_in_the_requested_size(): void
    {
        foreach ([['any', 192], ['maskable', 512], ['apple', 180]] as [$purpose, $size]) {
            $response = $this->get("/pwa-icon/$purpose-$size.png?c=e11d48")->assertOk()->assertHeader('Content-Type', 'image/png');
            $info = getimagesizefromstring($response->getContent());

            $this->assertSame([$size, $size], [$info[0], $info[1]]);
        }
    }

    public function test_maskable_icon_is_full_bleed_while_any_icon_has_rounded_transparent_corners(): void
    {
        $maskable = imagecreatefromstring($this->get('/pwa-icon/maskable-192.png?c=1867f8')->getContent());
        $any = imagecreatefromstring($this->get('/pwa-icon/any-192.png?c=1867f8')->getContent());

        $this->assertSame(0, imagecolorsforindex($maskable, imagecolorat($maskable, 0, 0))['alpha'], 'maskable: Ecke deckend');
        $this->assertGreaterThan(100, imagecolorsforindex($any, imagecolorat($any, 0, 0))['alpha'], 'any: Ecke transparent');
    }

    public function test_icon_route_rejects_unknown_sizes_purposes_and_bad_colors(): void
    {
        $this->get('/pwa-icon/any-9999.png')->assertNotFound();
        $this->get('/pwa-icon/evil-192.png')->assertNotFound();
        $this->get('/pwa-icon/any-192.png?c=zzzzzz')->assertOk(); // fällt auf die Standardfarbe zurück
    }

    public function test_service_worker_is_served_fresh_with_root_scope(): void
    {
        $response = $this->get('/sw.js')->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("vt-static-' + VERSION", $response->getContent());
        $this->assertStringContainsString("const VERSION = '".config('tickets.version')."'", $response->getContent());
        $this->assertStringNotContainsString('__VERSION__', $response->getContent());
    }

    public function test_offline_page_and_session_expired_page_render(): void
    {
        $this->get('/offline')->assertOk()->assertSee('Keine Verbindung');
        $this->view('errors.419')->assertSee('Sitzung ist abgelaufen');
    }

    public function test_pages_carry_ios_and_android_app_meta_tags(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        foreach ([
            'rel="manifest"', 'name="theme-color"', 'viewport-fit=cover', 'name="apple-mobile-web-app-capable"',
            'name="mobile-web-app-capable"', 'rel="apple-touch-icon"', 'apple-mobile-web-app-status-bar-style',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringNotContainsString('maximum-scale', $html, 'Zoomen muss möglich bleiben (Barrierefreiheit)');
    }
}
