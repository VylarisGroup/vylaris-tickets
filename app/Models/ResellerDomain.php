<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerDomain extends Model
{
    /** Unverifizierte Domains werden nach dieser Zeit entfernt. */
    public const PENDING_DAYS = 14;

    protected $fillable = ['reseller_id', 'domain', 'verified_key', 'verified_at', 'last_checked_at', 'last_error', 'plesk_alias_at', 'plesk_cert_at', 'plesk_error'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'last_checked_at' => 'datetime', 'plesk_alias_at' => 'datetime', 'plesk_cert_at' => 'datetime'];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
