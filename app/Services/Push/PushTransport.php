<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

interface PushTransport
{
    /** Sendet eine Nachricht. Rückgabe false = Abo ist abgelaufen/ungültig und soll gelöscht werden. */
    public function send(PushSubscription $subscription, array $payload): bool;
}
