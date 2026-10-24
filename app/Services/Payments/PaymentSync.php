<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

/** Gleicht den Zahlungsstatus einer offenen Bestellung beim Anbieter ab (Webhook oder Rückkehr des Kunden). */
class PaymentSync
{
    public function __construct(private OrderService $orders) {}

    public function sync(Order $order): Order
    {
        if (! $order->isPending() || ! $order->provider_ref || ! in_array($order->payment_method, ['mollie', 'stripe'], true)) {
            return $order;
        }

        try {
            $status = PaymentGateway::for($order->payment_method, $order->organizer)->status($order->provider_ref);
        } catch (\Throwable $e) {
            Log::warning('Zahlungsstatus nicht abrufbar', ['order' => $order->number, 'error' => $e->getMessage()]);

            return $order;
        }

        return match ($status) {
            'paid' => $this->orders->markPaid($order),
            'failed' => $this->orders->expire($order),
            default => $order,
        };
    }
}
