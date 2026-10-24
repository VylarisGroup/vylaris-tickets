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
            'hero' => $this->heroImage($order->event, $order->event->organizer->ticketColor()),
            'tint' => $this->mix($order->event->organizer->ticketColor(), '#ffffff', 0.9),
            'soft' => $this->mix($order->event->organizer->ticketColor(), '#ffffff', 0.78),
            'dark' => $this->mix($order->event->organizer->ticketColor(), '#0b1020', 0.55),
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

    private const HERO_W = 1474;

    private const HERO_H = 430;

    /** Kopfbild des Tickets (Cover oder Farbverlauf) als JPEG-Data-URI, passend zum Seitenverhältnis des Kopfbereichs. */
    private function heroImage(\App\Models\Event $event, string $color): ?string
    {
        try {
            [$r, $g, $b] = $this->rgb($color);
            [$dr, $dg, $db] = $this->rgb($this->mix($color, '#0b1020', 0.55));
            $img = imagecreatetruecolor(self::HERO_W, self::HERO_H);
            imagealphablending($img, true);

            $cover = null;
            if ($event->cover_path) {
                $raw = \Illuminate\Support\Facades\Storage::disk('public')->get($event->cover_path);
                $cover = $raw ? @imagecreatefromstring($raw) : null;
            }

            if ($cover) {
                // «cover»-Zuschnitt: Bild füllt den Bereich, der Überschuss wird mittig abgeschnitten.
                $sw = imagesx($cover);
                $sh = imagesy($cover);
                $scale = max(self::HERO_W / $sw, self::HERO_H / $sh);
                $cw = (int) round(self::HERO_W / $scale);
                $ch = (int) round(self::HERO_H / $scale);
                imagecopyresampled($img, $cover, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), self::HERO_W, self::HERO_H, $cw, $ch);

                // Dunkler Farbschleier, links stärker, damit weisse Schrift gut lesbar bleibt.
                for ($x = 0; $x < self::HERO_W; $x++) {
                    $a = 0.88 - 0.5 * ($x / self::HERO_W);
                    imageline($img, $x, 0, $x, self::HERO_H, imagecolorallocatealpha($img, $dr, $dg, $db, (int) round(127 * (1 - $a))));
                }
                for ($y = (int) (self::HERO_H * 0.5); $y < self::HERO_H; $y++) {
                    $a = 0.45 * (($y - self::HERO_H * 0.5) / (self::HERO_H * 0.5));
                    imageline($img, 0, $y, self::HERO_W, $y, imagecolorallocatealpha($img, 5, 8, 18, (int) round(127 * (1 - $a))));
                }
            } else {
                // Diagonaler Verlauf von der dunklen zur hellen Variante der Farbe.
                [$lr, $lg, $lb] = $this->rgb($this->mix($color, '#ffffff', 0.12));
                for ($x = 0; $x < self::HERO_W; $x++) {
                    $t = $x / self::HERO_W;
                    imageline($img, $x, 0, $x, self::HERO_H, imagecolorallocate($img, (int) ($dr + ($lr - $dr) * $t), (int) ($dg + ($lg - $dg) * $t), (int) ($db + ($lb - $db) * $t)));
                }
                // Weiche Kreise als Dekor
                foreach ([[1250, 60, 520, 14], [1420, 330, 380, 10], [1040, 380, 260, 8]] as [$cx, $cy, $d, $alpha]) {
                    imagefilledellipse($img, $cx, $cy, $d, $d, imagecolorallocatealpha($img, 255, 255, 255, 127 - $alpha));
                }
            }

            // Farbstreifen am unteren Rand
            imagefilledrectangle($img, 0, self::HERO_H - 10, self::HERO_W, self::HERO_H, imagecolorallocate($img, $r, $g, $b));

            ob_start();
            imagejpeg($img, null, 84);

            return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function rgb(string $hex): array
    {
        return sscanf(ltrim($hex, '#'), '%02x%02x%02x');
    }

    /** Mischt $a mit $b; $ratio = Anteil von $b (0..1). */
    private function mix(string $a, string $b, float $ratio): string
    {
        [$ar, $ag, $ab] = $this->rgb($a);
        [$br, $bg, $bb] = $this->rgb($b);

        return sprintf('#%02x%02x%02x', (int) round($ar + ($br - $ar) * $ratio), (int) round($ag + ($bg - $ag) * $ratio), (int) round($ab + ($bb - $ab) * $ratio));
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
