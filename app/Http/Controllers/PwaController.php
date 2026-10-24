<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** Web-App-Manifest, Icons, Service Worker und Offline-Seite (pro Mandant/Reseller gebrandet). */
class PwaController extends Controller
{
    private const SIZES = [180, 192, 512];

    public function manifest()
    {
        $brand = view()->shared('brand');
        $color = $this->color($brand['color']);

        $icon = fn (int $size, string $purpose) => [
            'src' => route('pwa.icon', ['purpose' => $purpose, 'size' => $size, 'c' => ltrim($color, '#')], false),
            'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => $purpose,
        ];

        // «any» und «maskable» getrennt deklarieren (kombinierte Icons sehen auf manchen Plattformen falsch zugeschnitten aus).
        return response()->json([
            'id' => '/',
            'name' => $brand['name'],
            'short_name' => mb_substr($brand['name'], 0, 12),
            'description' => 'Tickets, Abendkasse und Einlass',
            'lang' => 'de',
            'dir' => 'ltr',
            'start_url' => '/dashboard?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#080c19',
            'theme_color' => $color,
            'categories' => ['business', 'shopping'],
            'prefer_related_applications' => false,
            'icons' => [$icon(192, 'any'), $icon(512, 'any'), $icon(192, 'maskable'), $icon(512, 'maskable')],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function icon(string $purpose, int $size, ?string $c = null)
    {
        abort_unless(in_array($purpose, ['any', 'maskable', 'apple'], true) && in_array($size, self::SIZES, true), 404);

        $color = $this->color('#'.ltrim((string) request()->query('c', config('tickets.brand_color')), '#'));

        $png = Cache::remember("pwa.icon.$purpose.$size.$color", 86400, fn () => base64_encode($this->draw($size, $color, $purpose)));

        return response(base64_decode($png), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function serviceWorker(): Response
    {
        $js = str_replace('__VERSION__', config('tickets.version'), file_get_contents(resource_path('pwa/sw.js')));

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            // Safari und Chrome sollen den Service Worker immer frisch prüfen.
            'Cache-Control' => 'no-cache, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function domainCheck(\Illuminate\Http\Request $request)
    {
        $token = config('tickets.domain_check_token');
        abort_if($token && ! hash_equals((string) $token, (string) $request->query('token')), 403);

        $domain = strtolower(trim((string) $request->query('domain')));
        $base = strtolower((string) config('tickets.base_domain'));

        $known = $domain !== '' && (
            \App\Models\ResellerDomain::where('verified_key', $domain)->exists()
            || \App\Models\Reseller::where('domain', $domain)->exists()
            || ($base !== '' && str_ends_with($domain, '.'.$base) && \App\Models\Reseller::where('slug', substr($domain, 0, -strlen('.'.$base)))->exists())
        );

        abort_unless($known, 404);

        return response('ok');
    }

    public function offline()
    {
        return view('pwa.offline');
    }

    // ---------------------------------------------------------------------------------------------

    private function color(?string $hex): string
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : '#1867f8';
    }

    /** Zeichnet das Icon (Ticket-Symbol) ohne Schriftarten, damit es auf jedem Server funktioniert. */
    private function draw(int $size, string $color, string $purpose): string
    {
        $s = $size * 4; // Supersampling für glatte Kanten
        $img = imagecreatetruecolor($s, $s);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $bg = imagecolorallocate($img, $r, $g, $b);
        $white = imagecolorallocate($img, 255, 255, 255);
        $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);

        imagefill($img, 0, 0, $clear);
        imagealphablending($img, true);

        if ($purpose === 'any') {
            $this->roundRect($img, 0, 0, $s - 1, $s - 1, (int) ($s * 0.22), $bg);
        } else {
            imagefilledrectangle($img, 0, 0, $s, $s, $bg); // maskable/apple: randlos, der Zuschnitt erfolgt durch die Plattform
        }

        // Ticket innerhalb der Sicherheitszone (ca. 60 % der Fläche)
        $w = (int) ($s * 0.50);
        $h = (int) ($s * 0.34);
        $x = (int) (($s - $w) / 2);
        $y = (int) (($s - $h) / 2);
        $this->roundRect($img, $x, $y, $x + $w, $y + $h, (int) ($h * 0.16), $white);

        $notch = (int) ($h * 0.15);
        imagefilledellipse($img, $x, $y + (int) ($h / 2), $notch * 2, $notch * 2, $bg);
        imagefilledellipse($img, $x + $w, $y + (int) ($h / 2), $notch * 2, $notch * 2, $bg);

        $px = $x + (int) ($w * 0.68);
        $dash = max(2, (int) ($s * 0.012));
        for ($yy = $y + (int) ($h * 0.12); $yy < $y + $h - (int) ($h * 0.12); $yy += $dash * 3) {
            imagefilledrectangle($img, $px, $yy, $px + $dash, $yy + $dash * 2, $bg);
        }

        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $s, $s);

        ob_start();
        imagepng($out, null, 9);

        return (string) ob_get_clean();
    }

    private function roundRect($img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }
}
