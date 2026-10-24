<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ScanLog;
use App\Models\Ticket;

/** Einlass-Logik: Ticket prüfen und atomar einlösen. Genutzt vom Browser-Scanner und vom gekoppelten Handy. */
class CheckinService
{
    /** Löst ein Ticket anhand des gescannten/eingegebenen Codes ein. */
    public function checkCode(Event $event, string $input, ?int $userId, ?int $pairingId = null, string $mode = 'in'): array
    {
        $ticket = Ticket::with(['order', 'ticketType', 'seat.section'])
            ->where('event_id', $event->id)->where('code', strtoupper($this->extractCode($input)))->first();

        if (! $ticket) {
            return $this->result($event, 'invalid', 'Ungültiger Code', 'Dieses Ticket gehört nicht zu diesem Event.', null, $userId, $pairingId);
        }

        return $this->admit($event, $ticket, $userId, $pairingId, $mode);
    }

    /** $mode «in»: Einlass (inkl. Wiedereintritt nach Ausgang), «out»: Ausgang erfassen. */
    public function admit(Event $event, Ticket $ticket, ?int $userId, ?int $pairingId = null, string $mode = 'in'): array
    {
        $state = $this->state($ticket);

        if ($state === 'cancelled') {
            return $this->result($event, 'invalid', 'Ticket nicht gültig', 'Die Bestellung wurde storniert oder erstattet.', $ticket, $userId, $pairingId);
        }
        if ($state === 'unpaid') {
            return $this->result($event, 'invalid', 'Noch nicht bezahlt', 'Zu dieser Bestellung ist keine Zahlung eingegangen.', $ticket, $userId, $pairingId);
        }

        if ($mode === 'out') {
            return $this->checkOut($event, $ticket, $userId, $pairingId);
        }

        // Wiedereintritt: das Ticket war schon einmal drin, ist aber ausgetreten.
        if ($ticket->checked_in_at && $ticket->checked_out_at) {
            $back = Ticket::whereKey($ticket->id)->whereNotNull('checked_out_at')->update(['checked_out_at' => null]);

            return $back
                ? $this->result($event, 'ok', 'Wiedereintritt', null, $ticket, $userId, $pairingId)
                : $this->result($event, 'duplicate', 'Bereits eingelöst', 'Dieses Ticket ist bereits im Haus.', $ticket, $userId, $pairingId);
        }

        // Atomar: nur der erste Scan setzt checked_in_at, parallele Scans sehen 0 betroffene Zeilen.
        $updated = Ticket::whereKey($ticket->id)->whereNull('checked_in_at')->update(['checked_in_at' => now(), 'checked_in_by' => $userId]);

        if ($updated === 0) {
            $ticket->refresh();

            return $this->result($event, 'duplicate', 'Bereits eingelöst', 'Eingelöst um '.$ticket->checked_in_at->format('H:i:s').' Uhr.', $ticket, $userId, $pairingId);
        }

        return $this->result($event, 'ok', 'Einlass frei', null, $ticket, $userId, $pairingId);
    }

    private function checkOut(Event $event, Ticket $ticket, ?int $userId, ?int $pairingId): array
    {
        if (! $ticket->checked_in_at) {
            return $this->result($event, 'invalid', 'Noch nicht eingelassen', 'Dieses Ticket wurde noch nicht eingelöst.', $ticket, $userId, $pairingId);
        }

        $updated = Ticket::whereKey($ticket->id)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->update(['checked_out_at' => now()]);

        if ($updated === 0) {
            $ticket->refresh();

            return $this->result($event, 'duplicate', 'Bereits ausgetreten', 'Ausgang um '.$ticket->checked_out_at?->format('H:i:s').' Uhr erfasst.', $ticket, $userId, $pairingId);
        }

        return $this->result($event, 'out', 'Ausgang erfasst', null, $ticket, $userId, $pairingId);
    }

    public function state(Ticket $t): string
    {
        if ($t->status !== 'valid' || in_array($t->order->status, ['cancelled', 'expired', 'refunded'], true)) {
            return 'cancelled';
        }
        if (! $t->order->isPaid()) {
            return 'unpaid';
        }

        return $t->checked_in_at ? 'used' : 'valid';
    }

    public function stats(Event $event): array
    {
        $base = Ticket::where('event_id', $event->id)->where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'));

        return [
            'total' => (clone $base)->count(),
            'checked_in' => (clone $base)->whereNotNull('checked_in_at')->count(),
            'inside' => (clone $base)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
        ];
    }

    private function result(Event $event, string $status, string $title, ?string $detail, ?Ticket $ticket, ?int $userId, ?int $pairingId): array
    {
        $info = $ticket ? [
            'id' => $ticket->id, 'name' => $ticket->holder_name ?: $ticket->order->customer_name, 'type' => $ticket->ticketType->name,
            'seat' => $ticket->seat ? $ticket->seat->section->name.', '.$ticket->seat->label : null, 'order' => $ticket->order->number,
        ] : null;

        ScanLog::create([
            'event_id' => $event->id, 'pairing_id' => $pairingId, 'ticket_id' => $ticket?->id, 'user_id' => $userId,
            'status' => $status, 'title' => $title, 'detail' => $detail,
            'holder' => $info['name'] ?? null, 'ticket_type' => $info['type'] ?? null, 'seat' => $info['seat'] ?? null, 'order_number' => $info['order'] ?? null,
        ]);

        return ['status' => $status, 'title' => $title, 'detail' => $detail, 'ticket' => $info, 'stats' => $this->stats($event)];
    }

    /** Akzeptiert den reinen Code oder eine URL, die den Code enthält. */
    private function extractCode(string $input): string
    {
        $input = trim($input);

        return str_contains($input, '/') ? basename(parse_url($input, PHP_URL_PATH) ?: $input) : $input;
    }
}
