<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = ['organizer_id', 'event_id', 'user_id', 'opening_float_cents', 'opened_at', 'closed_at', 'counted_cash_cents', 'note'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Bargeld, das laut System in der Kasse sein sollte. */
    public function expectedCashCents(): int
    {
        return $this->opening_float_cents
            + (int) $this->orders()->where('status', 'paid')->where('payment_method', 'cash')->sum('total_cents');
    }
}
