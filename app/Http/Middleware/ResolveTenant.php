<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
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
        $reseller = $this->findReseller($request->getHost());

        if ($reseller && ! $reseller->is_active) {
            abort(404);
        }

        app()->instance('tenant.reseller', $reseller);

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
