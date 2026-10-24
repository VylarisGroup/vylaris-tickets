<?php

namespace App\Http\Middleware;

use App\Services\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vor der ersten Einrichtung: legt .env und APP_KEY automatisch an und stellt Sitzungen/Cache auf Dateien um,
 * damit der Assistent ohne Datenbank laufen kann. Läuft vor der Cookie-Verschlüsselung.
 */
class PrepareInstallation
{
    public function __construct(private Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installer->enabled() || $this->installer->isInstalled()) {
            return $next($request);
        }

        $env = $this->installer->env();
        $set = [];

        if (! $env->exists()) {
            $example = base_path('.env.example');
            $created = is_file($example) ? @copy($example, $env->path()) : @file_put_contents($env->path(), '') !== false;
            $created ? $set['APP_URL'] = rtrim($request->getSchemeAndHttpHost(), '/') : null;
        }

        if (blank(config('app.key'))) {
            $set['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }
        foreach (['SESSION_DRIVER' => ['session.driver', 'file'], 'CACHE_STORE' => ['cache.default', 'file'], 'QUEUE_CONNECTION' => ['queue.default', 'sync']] as $key => [$cfg, $safe]) {
            if (config($cfg) === 'database') {
                $set[$key] = $safe;
            }
        }

        if ($set === []) {
            return $next($request);
        }

        // Schon einmal versucht (oder .env nicht beschreibbar): nur zur Laufzeit überbrücken, damit die Prüfseite erscheint.
        // Schon jetzt gültig machen: auch Terminate-Middleware (Cookies/Sitzung) brauchen den Schlüssel in dieser Anfrage.
        if (isset($set['APP_KEY'])) {
            config(['app.key' => $set['APP_KEY']]);
        }

        if (! $request->query('_prep') && $env->writable()) {
            try {
                $env->set($set);

                // Neu laden, damit die frisch geschriebene .env gilt.
                return redirect($request->isMethod('GET') ? $this->withMarker($request) : '/installation?_prep=1');
            } catch (\Throwable) {
                // fällt auf die Laufzeit-Überbrückung zurück
            }
        }

        config([
            'app.key' => blank(config('app.key')) ? $set['APP_KEY'] : config('app.key'),
            'session.driver' => 'array',
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);

        return $next($request);
    }

    private function withMarker(Request $request): string
    {
        $url = $request->url();
        $query = $request->query();
        $query['_prep'] = 1;

        return $url.'?'.http_build_query($query);
    }
}
