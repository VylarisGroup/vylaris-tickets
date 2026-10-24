<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    protected $fillable = ['event_id', 'seat_section_id', 'seat_floor_id', 'kind', 'capacity', 'table_mode', 'row_label', 'number', 'label', 'x', 'y', 'is_blocked'];

    protected function casts(): array
    {
        return ['is_blocked' => 'boolean', 'x' => 'integer', 'y' => 'integer', 'number' => 'integer', 'capacity' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatSection::class, 'seat_section_id');
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(SeatFloor::class, 'seat_floor_id');
    }

    public function isTable(): bool
    {
        return $this->kind === 'table';
    }

    /** Belegungsschlüssel eines Sitzes (Unique-Index auf tickets.seat_key). */
    public function key(int $slot = 0): int
    {
        return $this->id * 1000 + $slot;
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
