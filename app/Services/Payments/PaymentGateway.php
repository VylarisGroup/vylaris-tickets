<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Organizer;
use InvalidArgumentException;

abstract class PaymentGateway
{
    public function __construct(protected Organizer $organizer, protected string $apiKey) {}

    public static function for(string $method, Organizer $organizer): self
    {
        $settings = $organizer->payment_settings ?? [];

        return match ($method) {
            'mollie' => new MollieGateway($organizer, $settings['mollie_api_key'] ?? throw new InvalidArgumentException('Mollie ist nicht eingerichtet.')),
            'stripe' => new StripeGateway($organizer, $settings['stripe_secret_key'] ?? throw new InvalidArgumentException('Stripe ist nicht eingerichtet.')),
            default => throw new InvalidArgumentException("Unbekannte Zahlungsart: {$method}"),
        };
    }

    /** Startet die Zahlung und speichert provider_ref und payment_url an der Bestellung. */
    abstract public function create(Order $order): void;

    /** Fragt den Zahlungsstatus beim Anbieter ab: paid | pending | failed. */
    abstract public function status(string $providerRef): string;

    abstract public function refund(Order $order): void;

    /** Webhook-Adresse nur setzen, wenn sie öffentlich erreichbar sein kann. */
    protected function publicWebhookUrl(string $name): ?string
    {
        $url = route($name, $this->organizer);
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
            return null;
        }

        return $url;
    }
}
