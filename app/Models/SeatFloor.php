<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Stockwerk oder Raum eines Saalplans, optional mit hochgeladenem Hallenplan als Hintergrund. */
class SeatFloor extends Model
{
    protected $fillable = ['event_id', 'name', 'sort', 'plan_path', 'plan_w', 'plan_h'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function planUrl(): ?string
    {
        return $this->plan_path ? Storage::disk('public')->url($this->plan_path) : null;
    }

    /** Höhe der Zeichenfläche bei fester Breite 1000 (Seitenverhältnis des Plans, sonst 640). */
    public function canvasHeight(): int
    {
        return $this->plan_w && $this->plan_h ? (int) round(1000 * $this->plan_h / $this->plan_w) : 640;
    }
}
