<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushTransport implements PushTransport
{
    private ?WebPush $client = null;

    public function send(PushSubscription $subscription, array $payload): bool
    {
        $this->client ??= new WebPush([
            'VAPID' => ['subject' => Vapid::subject(), 'publicKey' => Vapid::publicKey(), 'privateKey' => Vapid::keys()['privateKey']],
        ], ['TTL' => 3600, 'urgency' => 'normal'], new \GuzzleHttp\Client(['timeout' => 8, 'connect_timeout' => 5, 'http_errors' => false]), null, null, null, new \Psr\Log\NullLogger);

        $report = $this->client->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => ['p256dh' => $subscription->p256dh, 'auth' => $subscription->auth],
                'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
            ]),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        if ($report->isSubscriptionExpired()) {
            return false;
        }
        if (! $report->isSuccess()) {
            logger()->warning('Push fehlgeschlagen', ['reason' => $report->getReason()]);
        }

        return true;
    }
}
