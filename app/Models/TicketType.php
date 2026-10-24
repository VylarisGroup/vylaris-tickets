<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'event_id', 'name', 'description', 'price_cents', 'quota', 'max_per_order',
        'sales_start_at', 'sales_end_at', 'sort', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'quota' => 'integer',
            'max_per_order' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SeatSection::class);
    }

    /** Anzahl belegter Plätze (bezahlt oder offen und nicht abgelaufen). */
    public function sold(): int
    {
        return Ticket::holding()->where('ticket_type_id', $this->id)->count();
    }

    /** Verbleibende Plätze; null = unbegrenzt. */
    public function remaining(): ?int
    {
        return $this->quota === null ? null : max(0, $this->quota - $this->sold());
    }

    /** Gehört die Ticketart zu einem Sitzbereich (Verkauf nur über Platzwahl)? */
    public function isSeated(): bool
    {
        return $this->sections()->exists();
    }

    public function price(): string
    {
        return $this->price_cents === 0 ? 'Gratis' : Money::format($this->price_cents, $this->event->currency);
    }
}
