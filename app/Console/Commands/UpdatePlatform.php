<?php

namespace App\Console\Commands;

use App\Services\Updater;
use Illuminate\Console\Command;

class UpdatePlatform extends Command
{
    protected $signature = 'tickets:update {--check : Nur prüfen, nichts installieren}';

    protected $description = 'Installiert die neueste Plattform-Version (wie in der Administration, aber über die Konsole)';

    public function handle(Updater $updater): int
    {
        try {
            $latest = $updater->latest(true);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Installiert: '.$updater->currentVersion().' · Neueste: '.$latest['version']);
        $info = $updater->dependencyInfo();
        $this->line('Composer: '.($info['composer'] ?? 'nicht gefunden').' · PHP-CLI: '.($info['php'] ?? 'nicht gefunden').' · vendor.zip: '.($latest['vendor_url'] ? 'vorhanden' : 'nicht vorhanden'));

        if (! $latest['newer']) {
            $this->info('Du verwendest die neueste Version.');

            return self::SUCCESS;
        }
        if ($this->option('check')) {
            return self::SUCCESS;
        }

        try {
            $updater->install($latest);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Auf Version '.$latest['version'].' aktualisiert.');

        return self::SUCCESS;
    }
}
