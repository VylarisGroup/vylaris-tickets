<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Services\Installer;
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

        $byDomain = Reseller::where('domain', $host)->first();
        if ($byDomain) {
            return $byDomain;
        }

        return null;
    }
}
