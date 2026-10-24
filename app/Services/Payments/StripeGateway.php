<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class StripeGateway extends PaymentGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    private function http()
    {
        return Http::withToken($this->apiKey)->acceptJson()->asForm()->timeout(20);
    }

    public function create(Order $order): void
    {
        $response = $this->http()->post(self::BASE.'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => route('order.show', $order->access_token),
            'cancel_url' => route('order.show', $order->access_token),
            'client_reference_id' => $order->number,
            'customer_email' => $order->customer_email,
            'expires_at' => now()->addMinutes(30)->timestamp,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $order->total_cents,
                    'product_data' => ['name' => 'Tickets '.$order->event->title.' ('.$order->number.')'],
                ],
            ]],
            'metadata' => ['order' => $order->number],
        ])->throw()->json();

        $order->update(['provider_ref' => $response['id'], 'payment_url' => $response['url'] ?? null]);
    }

    public function status(string $providerRef): string
    {
        $session = $this->http()->get(self::BASE.'/checkout/sessions/'.$providerRef)->throw()->json();

        if (($session['payment_status'] ?? '') === 'paid') {
            return 'paid';
        }

        return ($session['status'] ?? '') === 'expired' ? 'failed' : 'pending';
    }

    public function refund(Order $order): void
    {
        $session = $this->http()->get(self::BASE.'/checkout/sessions/'.$order->provider_ref)->throw()->json();

        $this->http()->post(self::BASE.'/refunds', ['payment_intent' => $session['payment_intent']])->throw();
    }
}
