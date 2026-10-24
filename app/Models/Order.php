<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'organizer_id', 'event_id', 'number', 'access_token', 'status', 'channel', 'payment_method',
        'customer_name', 'customer_email', 'currency', 'subtotal_cents', 'discount_cents', 'total_cents',
        'vat_cents', 'voucher_id', 'voucher_code', 'provider_ref', 'payment_url', 'paid_at', 'expires_at',
        'cancelled_at', 'cash_session_id', 'created_by',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'vat_cents' => 'integer',
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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function total(): string
    {
        return $this->total_cents === 0 ? 'Gratis' : Money::format($this->total_cents, $this->currency);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Offen',
            'paid' => 'Bezahlt',
            'cancelled' => 'Storniert',
            'expired' => 'Abgelaufen',
            'refunded' => 'Rückerstattet',
            default => $this->status,
        };
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'mollie' => 'Mollie',
            'stripe' => 'Kreditkarte (Stripe)',
            'bank' => 'Banküberweisung',
            'cash' => 'Bargeld',
            'external' => 'Terminal / TWINT',
            'free' => 'Gratis',
            default => '–',
        };
    }
}
