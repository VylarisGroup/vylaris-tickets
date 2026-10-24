<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizer extends Model
{
    protected $fillable = [
        'reseller_id', 'name', 'slug', 'contact_email', 'street', 'zip', 'city', 'country',
        'vat_number', 'vat_enabled', 'vat_rate', 'default_currency', 'iban', 'payment_settings', 'is_active',
    ];

    protected $hidden = ['payment_settings'];

    protected function casts(): array
    {
        return [
            'vat_enabled' => 'boolean',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
            // Mollie-/Stripe-Schlüssel liegen verschlüsselt in der Datenbank.
            'payment_settings' => 'encrypted:array',
        ];
    }

    /** Zahlungsarten, die der Veranstalter für Online-Verkäufe eingerichtet hat. */
    public function onlinePaymentMethods(): array
    {
        $settings = $this->payment_settings ?? [];
        $methods = [];

        if (! empty($settings['mollie_api_key'])) {
            $methods['mollie'] = 'Online bezahlen (Mollie: Karte, TWINT u. a.)';
        }
        if (! empty($settings['stripe_secret_key'])) {
            $methods['stripe'] = 'Kreditkarte (Stripe)';
        }
        if (! empty($this->iban)) {
            $methods['bank'] = 'Banküberweisung';
        }

        return $methods;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
