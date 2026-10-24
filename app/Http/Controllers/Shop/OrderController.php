<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentSync;
use App\Services\TicketPdf;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show(string $token, PaymentSync $sync, TicketPdf $pdf)
    {
        $order = Order::with('event.organizer.reseller', 'tickets.ticketType', 'tickets.seat.section')->where('access_token', $token)->firstOrFail();

        // Kehrt der Kunde vom Zahlungsanbieter zurück, kennen wir den Status ggf. noch nicht: direkt nachfragen.
        $order = $sync->sync($order)->load('event.organizer.reseller', 'tickets.ticketType', 'tickets.seat.section');

        $this->applyBrand($order);

        return view('shop.order', [
            'order' => $order,
            'event' => $order->event,
            'organizer' => $order->event->organizer,
            'tickets' => $order->tickets->where('status', 'valid'),
            'qr' => fn (string $code) => $pdf->qr($code),
        ]);
    }

    public function pdf(Request $request, string $token, TicketPdf $pdf)
    {
        $order = Order::with('event.organizer.reseller')->where('access_token', $token)->firstOrFail();

        abort_unless($order->isPaid(), 404);

        return response($pdf->render($order, $request->query('ticket')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="tickets-'.$order->number.'.pdf"',
        ]);
    }

    /** Die Bestellseite zeigt das Branding des Resellers, dem der Veranstalter gehört – unabhängig von der Domain. */
    private function applyBrand(Order $order): void
    {
        $reseller = $order->event->organizer->reseller;

        if ($reseller) {
            view()->share('brand', [
                'name' => $reseller->displayName(), 'color' => $reseller->color(), 'logo' => $reseller->logo_path,
                'support_email' => $reseller->support_email, 'reseller' => $reseller,
            ]);
        }
    }
}
