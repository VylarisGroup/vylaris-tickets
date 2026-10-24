<?php

namespace App\Services;

use App\Models\Order;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;

class TicketPdf
{
    /** Erzeugt ein PDF mit einem Ticket pro Seite. $onlyCode beschränkt auf ein einzelnes Ticket. */
    public function render(Order $order, ?string $onlyCode = null): string
    {
        $order->loadMissing('event.organizer.reseller', 'tickets.ticketType', 'tickets.seat.section');

        $tickets = $order->tickets->where('status', 'valid')
            ->when($onlyCode, fn ($c) => $c->where('code', $onlyCode))->values();

        $html = view('pdf.tickets', [
            'order' => $order,
            'event' => $order->event,
            'organizer' => $order->event->organizer,
            'tickets' => $tickets,
            'color' => $order->event->organizer->ticketColor(),
            'logo' => $this->logoData($order->event->organizer),
            'brandName' => $order->event->organizer->reseller?->displayName() ?? config('tickets.name'),
            'qr' => fn (string $code) => $this->qr($code),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** Logo als PNG-Data-URI (Dompdf liest keine WebP-Dateien und keine Remote-Bilder). */
    private function logoData(\App\Models\Organizer $organizer): ?string
    {
        if (! $organizer->logo_path) {
            return null;
        }

        try {
            $raw = \Illuminate\Support\Facades\Storage::disk('public')->get($organizer->logo_path);
            $img = $raw ? @imagecreatefromstring($raw) : false;
            if (! $img) {
                return null;
            }
            imagesavealpha($img, true);
            ob_start();
            imagepng($img);
            $png = ob_get_clean();

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function qr(string $content): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            'scale' => 8,
            'outputBase64' => true,
            'quietzoneSize' => 2,
        ])))->render($content);
    }
}
