<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Services\Installer;
use App\Models\ResellerDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ermittelt anhand des Hostnamens den Reseller (Whitelabel) und stellt das Branding bereit.
 * Ohne Treffer gilt das Standard-Design von Vylaris Tickets.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Vor der Einrichtung gibt es noch keine Datenbank: Standard-Design ohne Mandantensuche.
        if (! app(Installer::class)->isInstalled()) {
            app()->bind('tenant.reseller', fn () => null);
            View::share('brand', ['name' => config('tickets.name'), 'color' => config('tickets.brand_color'), 'logo' => null, 'support_email' => null, 'reseller' => null]);

            return $next($request);
        }

        $reseller = $this->findReseller($request->getHost());

        if ($reseller && ! $reseller->is_active) {
            abort(404);
        }

        app()->bind('tenant.reseller', fn () => $reseller);

        View::share('brand', [
            'name' => $reseller?->displayName() ?? config('tickets.name'),
            'color' => $reseller?->color() ?? config('tickets.brand_color'),
            'logo' => $reseller?->logo_path,
            'support_email' => $reseller?->support_email,
            'reseller' => $reseller,
        ]);

        return $next($request);
    }

    private function findReseller(string $host): ?Reseller
    {
        $host = strtolower($host);

        // Vom Reseller registrierte Domains zählen nur, wenn der CNAME bestätigt wurde.
        $verified = ResellerDomain::where('verified_key', $host)->first();
        if ($verified) {
            return $verified->reseller;
        }

        $byDomain = Reseller::where('domain', $host)->first();
        if ($byDomain) {
            return $byDomain;
        }

        $base = config('tickets.base_domain');
        if ($base && str_ends_with($host, '.'.strtolower($base))) {
            $slug = substr($host, 0, -strlen('.'.$base));

            if ($slug !== '' && ! str_contains($slug, '.')) {
                return Reseller::where('slug', $slug)->first();
            }
        }

        return null;
    }
}
