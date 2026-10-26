<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'order_id', 'event_id', 'ticket_type_id', 'seat_id', 'seat_key', 'code', 'holder_name', 'holder_birthdate', 'holder_email', 'holder_phone', 'holder_city',
        'price_cents', 'status', 'checked_in_at', 'checked_in_by', 'checked_out_at',
    ];

    protected function casts(): array
    {
        return ['holder_birthdate' => 'date', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'price_cents' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /** Angaben zur Person, die auf dem Ticket stehen: Geburtsdatum und Wohnort (E-Mail und Telefon bleiben beim Veranstalter). */
    public function holderDetails(): array
    {
        return array_filter([
            'Geburtsdatum' => $this->holder_birthdate?->format('d.m.Y'),
            'Wohnort' => $this->holder_city,
        ]);
    }

    /** Platzangabe für Tickets, Listen und Scanner: «Bereich, A5» bzw. «Bereich, Tisch 3 · Platz 2». */
    public function seatText(bool $withSection = true): ?string
    {
        if (! $this->seat) {
            return null;
        }

        $label = $this->seat->label;
        if ($this->seat->isTable()) {
            $slot = $this->seat_key ? $this->seat_key % 1000 : 0;
            $label .= ' · Platz '.($slot + 1);
        }

        return $withSection && $this->seat->section ? $this->seat->section->name.', '.$label : $label;
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /** Tickets, die Kontingent bzw. Platz belegen: bezahlt oder offen und noch nicht abgelaufen. */
    public function scopeHolding(Builder $query): Builder
    {
        return $query->where('tickets.status', 'valid')->whereHas('order', function (Builder $o) {
            $o->where('status', 'paid')->orWhere(fn (Builder $p) => $p->where('status', 'pending')->where('expires_at', '>', now()));
        });
    }

    /** Gültig und bezahlt (für den Einlass). */
    public function scopeAdmittable(Builder $query): Builder
    {
        return $query->where('tickets.status', 'valid')->whereHas('order', fn (Builder $o) => $o->where('status', 'paid'));
    }
}
