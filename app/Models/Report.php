<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const REASONS = [
        'spam' => 'Spam oder Betrug',
        'illegal' => 'Illegale Inhalte',
        'misleading' => 'Irreführende Angaben',
        'copyright' => 'Urheberrechtsverletzung',
        'other' => 'Sonstiges',
    ];

    protected $fillable = ['event_id', 'reason', 'message', 'reporter_email', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
