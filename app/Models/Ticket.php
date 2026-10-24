<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'order_id', 'event_id', 'ticket_type_id', 'seat_id', 'seat_key', 'code', 'holder_name',
        'price_cents', 'status', 'checked_in_at', 'checked_in_by',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'price_cents' => 'integer'];
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
