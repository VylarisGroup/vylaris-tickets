<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    protected $fillable = ['event_id', 'seat_section_id', 'row_label', 'number', 'label', 'x', 'y', 'is_blocked'];

    protected function casts(): array
    {
        return ['is_blocked' => 'boolean', 'x' => 'integer', 'y' => 'integer', 'number' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatSection::class, 'seat_section_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
