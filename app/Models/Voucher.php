<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'organizer_id', 'event_id', 'ticket_type_id', 'code', 'type', 'value', 'max_uses',
        'used_count', 'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /** Ist der Code zum Zeitpunkt für die angegebene Ticketart einlösbar? */
    public function isRedeemable(?TicketType $ticketType = null, ?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        return $this->is_active
            && ($this->max_uses === null || $this->used_count < $this->max_uses)
            && (! $this->valid_from || $this->valid_from->lte($at))
            && (! $this->valid_until || $this->valid_until->gte($at))
            && (! $ticketType || ! $this->ticket_type_id || $this->ticket_type_id === $ticketType->id)
            && (! $ticketType || ! $this->event_id || $this->event_id === $ticketType->event_id);
    }

    /** Rabatt in Rappen/Cent auf einen Einzelpreis (nie mehr als der Preis). */
    public function discountFor(int $unitPriceCents): int
    {
        $discount = $this->type === 'percent'
            ? (int) round($unitPriceCents * min($this->value, 100) / 100)
            : $this->value;

        return min($discount, $unitPriceCents);
    }
}
