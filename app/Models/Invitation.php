<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Einladung eines neuen Benutzers in einen Veranstalter. Der Token liegt nur als Hash in der Datenbank. */
class Invitation extends Model
{
    public const VALID_DAYS = 7;

    protected $fillable = ['organizer_id', 'email', 'name', 'role', 'token_hash', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /** Legt eine Einladung an und gibt sie samt Klartext-Token zurück. @return array{0:self,1:string} */
    public static function issue(Organizer $organizer, string $email, string $name, string $role): array
    {
        // Eine offene Einladung pro Person und Veranstalter.
        static::where('organizer_id', $organizer->id)->where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invitation = static::create([
            'organizer_id' => $organizer->id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        return [$invitation, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->first();
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at');
    }
}
