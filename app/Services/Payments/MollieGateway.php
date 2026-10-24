<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class MollieGateway extends PaymentGateway
{
    private const BASE = 'https://api.mollie.com/v2';

    private function http()
    {
        return Http::withToken($this->apiKey)->acceptJson()->asJson()->timeout(20);
    }

    public function create(Order $order): void
    {
        $payload = [
            'amount' => ['currency' => $order->currency, 'value' => number_format($order->total_cents / 100, 2, '.', '')],
            'description' => 'Tickets '.$order->event->title.' ('.$order->number.')',
            'redirectUrl' => route('order.show', $order->access_token),
            'metadata' => ['order' => $order->number],
            'locale' => 'de_CH',
        ];

        if ($webhook = $this->publicWebhookUrl('webhooks.mollie')) {
            $payload['webhookUrl'] = $webhook;
        }

        $response = $this->http()->post(self::BASE.'/payments', $payload)->throw()->json();

        $order->update([
            'provider_ref' => $response['id'],
            'payment_url' => $response['_links']['checkout']['href'] ?? null,
        ]);
    }

    public function status(string $providerRef): string
    {
        $payment = $this->http()->get(self::BASE.'/payments/'.$providerRef)->throw()->json();

        return match ($payment['status'] ?? '') {
            'paid' => 'paid',
            'failed', 'canceled', 'expired' => 'failed',
            default => 'pending',
        };
    }

    public function refund(Order $order): void
    {
        $this->http()->post(self::BASE.'/payments/'.$order->provider_ref.'/refunds', [
            'amount' => ['currency' => $order->currency, 'value' => number_format($order->total_cents / 100, 2, '.', '')],
            'description' => 'Rückerstattung '.$order->number,
        ])->throw();
    }
}
