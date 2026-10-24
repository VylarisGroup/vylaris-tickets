<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organizer;
use App\Services\Payments\PaymentSync;
use Illuminate\Http\Request;

/**
 * Webhooks werden nicht über Signaturen, sondern über Rückfrage beim Anbieter verifiziert:
 * Wir übernehmen nur die Zahlungs-ID und holen den Status mit dem Schlüssel des Veranstalters ab.
 */
class PaymentWebhookController extends Controller
{
    public function mollie(Request $request, Organizer $organizer, PaymentSync $sync)
    {
        return $this->handle($organizer, 'mollie', (string) $request->input('id'), $sync);
    }

    public function stripe(Request $request, Organizer $organizer, PaymentSync $sync)
    {
        $object = $request->input('data.object', []);
        $id = is_array($object) && ($object['object'] ?? '') === 'checkout.session' ? (string) ($object['id'] ?? '') : '';

        return $this->handle($organizer, 'stripe', $id, $sync);
    }

    private function handle(Organizer $organizer, string $method, string $ref, PaymentSync $sync)
    {
        if ($ref !== '') {
            $order = Order::with('organizer', 'event')->where('organizer_id', $organizer->id)
                ->where('payment_method', $method)->where('provider_ref', $ref)->first();

            if ($order) {
                $sync->sync($order);
            }
        }

        // Immer 200: Anbieter wiederholen sonst unnötig.
        return response('ok');
    }
}
