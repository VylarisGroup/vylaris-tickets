<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

/** Löst den Scheduler per URL aus, für Hoster ohne Befehlszeilen-Cron (Plesk: «URL abrufen»). */
class CronController extends Controller
{
    public function __invoke(string $token)
    {
        $expected = (string) config('tickets.cron_token');

        // Ohne konfigurierten Token existiert die Adresse nicht.
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        @set_time_limit(120);
        Artisan::call('schedule:run');

        return response('OK', 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
