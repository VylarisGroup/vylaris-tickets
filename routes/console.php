<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Cron-Eintrag: * * * * * php /pfad/zur/app/artisan schedule:run >> /dev/null 2>&1
Schedule::command('tickets:expire-orders')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:notify-waitlist')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tickets:verify-domains')->everyFifteenMinutes()->withoutOverlapping();

// Lebenszeichen, damit der Admin-Bereich erkennt, ob der Cron-Eintrag eingerichtet ist.
Schedule::call(fn () => Cache::put(\App\Services\SystemCheck::HEARTBEAT_KEY, now()->timestamp, 3600))->everyMinute()->name('heartbeat');
