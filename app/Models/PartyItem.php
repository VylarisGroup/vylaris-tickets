<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyItem extends Model
{
    protected $fillable = ['party_id', 'label', 'guest_id', 'added_by_guest'];

    protected function casts(): array
    {
        return ['added_by_guest' => 'boolean'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(PartyGuest::class, 'guest_id');
    }
}
