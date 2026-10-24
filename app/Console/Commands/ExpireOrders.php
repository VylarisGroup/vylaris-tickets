<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'tickets:expire-orders';

    protected $description = 'Gibt abgelaufene, unbezahlte Bestellungen frei und benachrichtigt die Warteliste';

    public function handle(OrderService $orders): int
    {
        $this->info($orders->expireDue().' Bestellung(en) freigegeben.');

        return self::SUCCESS;
    }
}
