<?php

use Illuminate\Support\Facades\Schedule;

// Cron-Eintrag: * * * * * php /pfad/zur/app/artisan schedule:run >> /dev/null 2>&1
Schedule::command('tickets:expire-orders')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:notify-waitlist')->everyFiveMinutes()->withoutOverlapping();
