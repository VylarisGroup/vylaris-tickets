<?php

namespace App\Http\Middleware;

use App\Services\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Leitet vor der Einrichtung auf den Assistenten um; danach ist der Assistent gesperrt. */
class RequireInstallation
{
    public function __construct(private Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installer->enabled()) {
            return $next($request);
        }

        $installerPath = $request->is('installation', 'installation/*');

        if ($this->installer->isInstalled()) {
            abort_if($installerPath, 404);

            return $next($request);
        }

        if ($installerPath || $request->is('sw.js', 'offline', 'manifest.webmanifest', 'pwa-icon/*', 'up')) {
            return $next($request);
        }

        return redirect('/installation');
    }
}
