<?php

namespace App\Console\Commands;

use App\Services\Push\PushNotifier;
use App\Services\Updater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckUpdates extends Command
{
    protected $signature = 'tickets:check-updates';

    protected $description = 'Prüft auf eine neue Plattform-Version und benachrichtigt Administratoren per Push';

    public function handle(Updater $updater, PushNotifier $push): int
    {
        try {
            $latest = $updater->latest(true);
        } catch (\Throwable $e) {
            $this->warn('Update-Prüfung nicht möglich: '.$e->getMessage());

            return self::SUCCESS;
        }

        if (! empty($latest['newer']) && Cache::add('push.update.'.$latest['version'], 1, 60 * 86400)) {
            $push->toAdmins('update', 'Neue Version '.$latest['version'], 'Jetzt in der Administration aktualisieren.', route('admin.update', [], false), 'update');
            $this->info('Push für Version '.$latest['version'].' gesendet.');
        }

        return self::SUCCESS;
    }
}
