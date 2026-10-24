<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Kopplung eines Smartphones als Kamera-Scanner für ein Event (Zugriff nur über das Token). */
class ScanPairing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public static function start(Event $event, ?int $userId, int $hours = 12): self
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::active()->where('code', $code)->exists());

        return self::create([
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'user_id' => $userId,
            'token' => Str::random(40), 'code' => $code, 'expires_at' => now()->addHours($hours),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** Schon in den letzten Sekunden ein Gerät aktiv? */
    public function isConnected(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds(20));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
