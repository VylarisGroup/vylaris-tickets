<?php

namespace App\Console\Commands;

use App\Services\DomainVerifier;
use Illuminate\Console\Command;

class VerifyDomains extends Command
{
    protected $signature = 'tickets:verify-domains';

    protected $description = 'Prüft ausstehende Reseller-Domains (CNAME) und entfernt zu alte, unbestätigte';

    public function handle(DomainVerifier $verifier): int
    {
        [$confirmed, $removed] = $verifier->sweep();
        $this->info("$confirmed Domain(s) bestätigt, $removed veraltete entfernt.");

        return self::SUCCESS;
    }
}
