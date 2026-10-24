<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Mail\BankTransferMail;
use App\Mail\OrderPaidMail;
use App\Models\CashSession;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderService
{
    public const ONLINE_HOLD_MINUTES = 30;

    public const BANK_HOLD_DAYS = 5;

    public const MAX_SEATS_PER_ORDER = 10;

    /**
     * Legt eine Bestellung an und reserviert Plätze/Kontingent.
     *
     * @param  array<int,int>  $items  ticket_type_id => Anzahl (Plätze ohne Platzwahl)
     * @param  array<int,int>  $seatIds  gewählte Sitzplätze
     * @param  array{name:string,email:?string}  $customer
     */
    public function place(
        Event $event,
        array $items,
        array $seatIds,
        array $customer,
        ?string $voucherCode,
        string $method,
        string $channel = 'online',
        ?User $by = null,
        ?CashSession $session = null,
    ): Order {
        $event->loadMissing('organizer.reseller');
        $organizer = $event->organizer;
        $door = $channel === 'door';

        try {
            $order = DB::transaction(function () use ($event, $organizer, $items, $seatIds, $customer, $voucherCode, $method, $door, $by, $session) {
                // Serialisiert gleichzeitige Bestellungen für dasselbe Event (Überverkauf verhindern).
                Event::whereKey($event->id)->lockForUpdate()->first();
                $this->expireDue($event);

                $this->assertSellable($event, $door);
                $lines = $this->buildLines($event, $items, $seatIds, $door);

                if ($lines === []) {
                    throw new CheckoutException('Bitte wähle mindestens ein Ticket aus.');
                }

                $subtotal = array_sum(array_column($lines, 'price'));
                [$voucher, $discount] = $this->applyVoucher($organizer, $event, $voucherCode, $lines);
                $total = max(0, $subtotal - $discount);

                $method = $this->resolveMethod($organizer, $method, $total, $door);

                $paidNow = $door || $method === 'free';
                $order = Order::create([
                    'organizer_id' => $organizer->id,
                    'event_id' => $event->id,
                    'number' => $this->nextNumber(),
                    'access_token' => Str::random(48),
                    'status' => $paidNow ? 'paid' : 'pending',
                    'channel' => $door ? 'door' : 'online',
                    'payment_method' => $method,
                    'customer_name' => $customer['name'] ?: 'Abendkasse',
                    'customer_email' => $customer['email'] ?? null,
                    'currency' => $event->currency,
                    'subtotal_cents' => $subtotal,
                    'discount_cents' => $discount,
                    'total_cents' => $total,
                    'vat_cents' => $this->vatPortion($organizer, $total),
                    'voucher_id' => $voucher?->id,
                    'voucher_code' => $voucher?->code,
                    'paid_at' => $paidNow ? now() : null,
                    'expires_at' => $paidNow ? null : now()->addMinutes($method === 'bank' ? self::BANK_HOLD_DAYS * 1440 : self::ONLINE_HOLD_MINUTES),
                    'cash_session_id' => $session?->id,
                    'created_by' => $by?->id,
                ]);

                $this->createTickets($order, $lines, $voucher, $discount, $subtotal);

                $voucher?->increment('used_count');

                return $order;
            });
        } catch (QueryException $e) {
            // Unique-Index auf tickets.seat_key: ein Platz wurde parallel vergeben.
            throw new CheckoutException('Mindestens ein gewählter Platz wurde soeben vergeben. Bitte wähle neu.');
        }

        $order->setRelation('event', $event);

        if ($order->isPaid()) {
            $this->sendConfirmation($order);
        } elseif (in_array($order->payment_method, ['mollie', 'stripe'], true)) {
            try {
                PaymentGateway::for($order->payment_method, $organizer)->create($order);
            } catch (\Throwable $e) {
                Log::error('Zahlung konnte nicht gestartet werden', ['order' => $order->number, 'error' => $e->getMessage()]);
                $this->expire($order);

                throw new CheckoutException('Die Zahlung konnte nicht gestartet werden. Bitte versuche es erneut oder wähle eine andere Zahlungsart.');
            }
        } elseif ($order->payment_method === 'bank') {
            $this->mail($order, new BankTransferMail($order));
        }

        $this->pushNewOrder($order);

        return $order;
    }

    /** Markiert eine Bestellung als bezahlt (idempotent) und verschickt die Tickets. */
    public function markPaid(Order $order, ?string $method = null): Order
    {
        $changed = DB::transaction(function () use ($order, $method) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return false;
            }

            if (in_array($locked->status, ['expired', 'cancelled'], true) && ! $this->revive($locked)) {
                Log::warning('Zahlung nach Ablauf eingegangen, Kapazität nicht mehr verfügbar', ['order' => $locked->number]);

                return false;
            }

            $locked->update(['status' => 'paid', 'paid_at' => now(), 'expires_at' => null, 'cancelled_at' => null, 'payment_method' => $method ?? $locked->payment_method]);

            return true;
        });

        $order->refresh();

        if ($changed) {
            $this->sendConfirmation($order);

            // Online-Zahlung (Webhook) eingegangen. Bankzahlungen bestätigt der Veranstalter selbst, dafür braucht er keine Push.
            if (in_array($order->payment_method, ['mollie', 'stripe'], true)) {
                $this->pushNewOrder($order);
            }
        }

        return $order;
    }

    /** Verschickt die Bestätigung bzw. Zahlungsinformation erneut. */
    public function resend(Order $order): void
    {
        $order->loadMissing('event');

        if ($order->isPaid()) {
            $this->sendConfirmation($order);
        } elseif ($order->isPending() && $order->payment_method === 'bank') {
            $this->mail($order, new BankTransferMail($order));
        }
    }

    public function cancel(Order $order, string $status = 'cancelled'): Order
    {
        $released = DB::transaction(function () use ($order, $status) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (in_array($locked->status, ['cancelled', 'expired', 'refunded'], true)) {
                return false;
            }

            $locked->tickets()->update(['status' => 'cancelled', 'seat_key' => null]);
            $locked->update(['status' => $status, 'cancelled_at' => now()]);

            if ($locked->voucher_id) {
                Voucher::whereKey($locked->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
            }

            return true;
        });

        $order->refresh();

        if ($released) {
            app(WaitlistService::class)->notifyFor($order->event);
        }

        return $order;
    }

    public function expire(Order $order): Order
    {
        return $this->cancel($order, 'expired');
    }

    /** Gibt abgelaufene offene Bestellungen frei (optional nur für ein Event). */
    public function expireDue(?Event $event = null): int
    {
        $count = 0;

        Order::where('status', 'pending')->where('expires_at', '<=', now())
            ->when($event, fn ($q) => $q->where('event_id', $event->id))
            ->get()->each(function (Order $order) use (&$count) {
                $this->expire($order);
                $count++;
            });

        return $count;
    }

    /** Erstattet eine bezahlte Bestellung (beim Anbieter, falls online bezahlt) und gibt Plätze frei. */
    public function refund(Order $order): Order
    {
        if (! $order->isPaid()) {
            return $order;
        }

        if (in_array($order->payment_method, ['mollie', 'stripe'], true) && $order->provider_ref && $order->total_cents > 0) {
            PaymentGateway::for($order->payment_method, $order->organizer)->refund($order);
        }

        return $this->cancel($order, 'refunded');
    }

    // ---------------------------------------------------------------------------------------------

    private function assertSellable(Event $event, bool $door): void
    {
        if ($door) {
            $ok = $event->status->value === 'published' && ! $event->is_blocked && $event->door_sales_enabled
                && $event->type->hasVenue() && now()->lte(($event->ends_at ?? $event->starts_at->copy()->addHours(12)));

            if (! $ok) {
                throw new CheckoutException('Für dieses Event ist kein Verkauf an der Abendkasse möglich.');
            }

            return;
        }

        if ($event->is_blocked) {
            throw new CheckoutException('Dieses Event ist gesperrt.');
        }

        if (! $event->isOnSale()) {
            throw new CheckoutException('Der Vorverkauf für dieses Event ist nicht geöffnet.');
        }
    }

    /** @return array<int,array{type:TicketType,seat:?Seat,price:int}> */
    private function buildLines(Event $event, array $items, array $seatIds, bool $door): array
    {
        $lines = [];
        $types = $event->ticketTypes()->get()->keyBy('id');

        foreach ($items as $typeId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }

            $type = $types->get((int) $typeId) ?? throw new CheckoutException('Unbekannte Ticketart.');

            if (! $type->is_active) {
                throw new CheckoutException("«{$type->name}» ist nicht verfügbar.");
            }
            if ($type->isSeated()) {
                throw new CheckoutException("«{$type->name}» wird über die Platzwahl verkauft.");
            }
            if (! $door) {
                $now = now();
                if (($type->sales_start_at && $type->sales_start_at->gt($now)) || ($type->sales_end_at && $type->sales_end_at->lt($now))) {
                    throw new CheckoutException("«{$type->name}» ist aktuell nicht im Verkauf.");
                }
                if ($qty > $type->max_per_order) {
                    throw new CheckoutException("Von «{$type->name}» sind maximal {$type->max_per_order} Tickets pro Bestellung möglich.");
                }
            }

            $remaining = $type->remaining();
            if ($remaining !== null && $qty > $remaining) {
                throw new CheckoutException($remaining === 0 ? "«{$type->name}» ist ausverkauft." : "Von «{$type->name}» sind nur noch {$remaining} Tickets verfügbar.", true, $type->id);
            }

            for ($i = 0; $i < $qty; $i++) {
                $lines[] = ['type' => $type, 'seat' => null, 'price' => $type->price_cents];
            }
        }

        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));

        if ($seatIds !== []) {
            if (! $event->seating_mode->isSeated()) {
                throw new CheckoutException('Für dieses Event gibt es keine Platzwahl.');
            }
            if (! $door && count($seatIds) > self::MAX_SEATS_PER_ORDER) {
                throw new CheckoutException('Pro Bestellung sind maximal '.self::MAX_SEATS_PER_ORDER.' Plätze möglich.');
            }

            $seats = Seat::with('section.ticketType')->where('event_id', $event->id)->whereIn('id', $seatIds)->get();

            if ($seats->count() !== count($seatIds)) {
                throw new CheckoutException('Ein gewählter Platz existiert nicht mehr.');
            }

            $taken = Ticket::holding()->whereIn('seat_id', $seatIds)->pluck('seat_id')->all();

            foreach ($seats as $seat) {
                $type = $seat->section->ticketType;

                if ($seat->is_blocked || ! $type || ! $type->is_active) {
                    throw new CheckoutException("Platz {$seat->label} ist nicht verkäuflich.");
                }
                if (in_array($seat->id, $taken, true)) {
                    throw new CheckoutException("Platz {$seat->label} wurde soeben vergeben. Bitte wähle neu.");
                }

                $lines[] = ['type' => $type, 'seat' => $seat, 'price' => $type->price_cents];
            }
        }

        return $lines;
    }

    /** @return array{0:?Voucher,1:int} */
    private function applyVoucher($organizer, Event $event, ?string $code, array $lines): array
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return [null, 0];
        }

        $voucher = $organizer->vouchers()->where('code', $code)->lockForUpdate()->first();

        if (! $voucher || ! $voucher->isRedeemable() || ($voucher->event_id && $voucher->event_id !== $event->id)) {
            throw new CheckoutException('Der Gutscheincode ist ungültig oder nicht mehr gültig.');
        }

        $discount = 0;
        foreach ($lines as $line) {
            if ($voucher->isRedeemable($line['type'])) {
                $discount += $voucher->discountFor($line['price']);
            }
        }

        if ($discount === 0 && array_sum(array_column($lines, 'price')) > 0) {
            throw new CheckoutException('Der Gutscheincode gilt nicht für die gewählten Tickets.');
        }

        return [$voucher, $discount];
    }

    private function resolveMethod($organizer, string $method, int $total, bool $door): string
    {
        if ($door) {
            if (! in_array($method, ['cash', 'external'], true)) {
                throw new CheckoutException('Ungültige Zahlungsart für die Abendkasse.');
            }

            return $method;
        }

        if ($total === 0) {
            return 'free';
        }

        if (! array_key_exists($method, $organizer->onlinePaymentMethods())) {
            throw new CheckoutException('Bitte wähle eine verfügbare Zahlungsart.');
        }

        return $method;
    }

    /** Enthaltene MWST bei Brutto-Preisen. */
    private function vatPortion($organizer, int $total): int
    {
        if (! $organizer->vat_enabled || $total <= 0) {
            return 0;
        }

        $rate = (float) $organizer->vat_rate;

        return (int) round($total * $rate / (100 + $rate));
    }

    private function createTickets(Order $order, array $lines, ?Voucher $voucher, int $discount, int $subtotal): void
    {
        $remainingDiscount = $discount;

        foreach ($lines as $i => $line) {
            $price = $line['price'];

            // Rabatt pro Ticket (nur für passende Ticketarten), Rest auf das letzte Ticket verteilt vermeiden wir bewusst.
            $off = ($voucher && $voucher->isRedeemable($line['type'])) ? min($voucher->discountFor($price), $remainingDiscount) : 0;
            $remainingDiscount -= $off;

            $order->tickets()->create([
                'event_id' => $order->event_id,
                'ticket_type_id' => $line['type']->id,
                'seat_id' => $line['seat']?->id,
                'seat_key' => $line['seat']?->id,
                'code' => strtoupper(Str::random(20)),
                'holder_name' => $order->customer_name,
                'price_cents' => $price - $off,
            ]);
        }
    }

    /** Versucht, eine abgelaufene Bestellung nach verspäteter Zahlung wiederherzustellen. */
    private function revive(Order $order): bool
    {
        $tickets = $order->tickets()->get();

        foreach ($tickets->groupBy('ticket_type_id') as $typeId => $group) {
            $type = TicketType::find($typeId);
            $remaining = $type?->remaining();

            if (! $type || ($remaining !== null && $group->whereNull('seat_id')->count() > $remaining)) {
                return false;
            }
        }

        foreach ($tickets->whereNotNull('seat_id') as $ticket) {
            if (Ticket::holding()->where('seat_id', $ticket->seat_id)->exists()) {
                return false;
            }
        }

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'valid', 'seat_key' => $ticket->seat_id]);
        }

        if ($order->voucher_id) {
            Voucher::whereKey($order->voucher_id)->increment('used_count');
        }

        return true;
    }

    private function nextNumber(): string
    {
        do {
            $number = 'VT-'.strtoupper(Str::random(8));
        } while (Order::where('number', $number)->exists());

        return $number;
    }

    private function sendConfirmation(Order $order): void
    {
        if ($order->customer_email) {
            $this->mail($order, new OrderPaidMail($order));
        }
    }

    /** Push an das Team: neue (bezahlte bzw. auf Überweisung wartende) Online-Bestellung und ausverkaufte Ticketarten. */
    private function pushNewOrder(Order $order): void
    {
        if ($order->channel === 'door') {
            return;
        }

        try {
            $order->loadMissing('event.organizer');
            $event = $order->event;
            $organizer = $event->organizer;
            $push = app(\App\Services\Push\PushNotifier::class);
            $url = route('organizer.orders.show', [$organizer->slug, $event->slug, $order->id], false);
            $count = $order->tickets()->count();
            $body = $order->customer_name.' · '.$count.' '.($count === 1 ? 'Ticket' : 'Tickets').' · '.$order->total().' · '.$event->title;

            if ($order->isPaid()) {
                $push->toOrganizer($organizer, 'order_paid', 'Neue Bestellung · '.$organizer->name, $body, $url, 'order-'.$order->id);
            } elseif ($order->isPending() && $order->payment_method === 'bank') {
                $push->toOrganizer($organizer, 'order_pending', 'Überweisung wartet · '.$organizer->name, $body, $url, 'order-'.$order->id);
            } else {
                return;
            }

            foreach ($event->ticketTypes()->whereNotNull('quota')->get() as $type) {
                if ($type->remaining() === 0 && \Illuminate\Support\Facades\Cache::add('push.soldout.'.$type->id, 1, 6 * 3600)) {
                    $push->toOrganizer($organizer, 'sold_out', 'Ausverkauft · '.$organizer->name, $type->name.' · '.$event->title, route('organizer.events.show', [$organizer->slug, $event->slug], false), 'soldout-'.$type->id);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function mail(Order $order, $mailable): void
    {
        if (! $order->customer_email) {
            return;
        }

        try {
            Mail::to($order->customer_email)->send($mailable);
        } catch (\Throwable $e) {
            // Ein Mailfehler darf eine bezahlte Bestellung nie scheitern lassen. Die Tickets bleiben über den Bestelllink abrufbar.
            Log::error('Bestell-Mail konnte nicht gesendet werden', ['order' => $order->number, 'error' => $e->getMessage()]);
        }
    }
}
