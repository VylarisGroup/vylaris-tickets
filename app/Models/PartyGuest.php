<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartyGuest extends Model
{
    public const RESPONSES = ['yes' => 'Zugesagt', 'maybe' => 'Vielleicht', 'no' => 'Abgesagt'];

    protected $fillable = ['party_id', 'name', 'email', 'token', 'response', 'companions', 'message', 'responded_at', 'invited_at', 'reminded_at', 'companion_names', 'answers', 'auto_reminded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'invited_at' => 'datetime', 'reminded_at' => 'datetime', 'auto_reminded_at' => 'datetime', 'companion_names' => 'array', 'answers' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $guest) {
            $guest->token ??= Str::random(32);
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function url(): string
    {
        return route('party.show', $this->token);
    }

    public function responseLabel(): string
    {
        return self::RESPONSES[$this->response] ?? 'Offen';
    }
}
