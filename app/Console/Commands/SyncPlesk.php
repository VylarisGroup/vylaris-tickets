<?php

namespace App\Console\Commands;

use App\Services\PleskSync;
use Illuminate\Console\Command;

class SyncPlesk extends Command
{
    protected $signature = 'tickets:sync-plesk';

    protected $description = 'Richtet bestätigte Reseller-Domains in Plesk ein (Domain-Alias und Let\'s-Encrypt-Zertifikat)';

    public function handle(PleskSync $plesk): int
    {
        if (! $plesk->enabled()) {
            $this->line('Plesk-Automatik ist nicht eingerichtet (PLESK_API_KEY und PLESK_DOMAIN).');

            return self::SUCCESS;
        }

        [$aliases, $cert] = $plesk->sync();
        $this->info("$aliases Alias(e) angelegt".($cert ? ', Zertifikat neu ausgestellt.' : '.'));

        return self::SUCCESS;
    }
}
