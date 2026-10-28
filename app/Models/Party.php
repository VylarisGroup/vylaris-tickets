<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Einladung (ohne Tickets): Gäste bekommen einen persönlichen Link und sagen zu oder ab. */
class Party extends Model
{
    /** Farbthemen der Einladungsseite: [Name, Farbe 1, Farbe 2, Akzent]. */
    public const THEMES = [
        'ocean' => ['Ozean', '#0ea5e9', '#6366f1', '#0369a1'],
        'sunset' => ['Sonnenuntergang', '#f97316', '#ec4899', '#c2410c'],
        'forest' => ['Wald', '#22c55e', '#0d9488', '#15803d'],
        'berry' => ['Beere', '#a855f7', '#e11d48', '#7e22ce'],
        'night' => ['Nacht', '#1e293b', '#4338ca', '#4f46e5'],
        'gold' => ['Gold', '#f59e0b', '#ef4444', '#b45309'],
    ];

    /** Schriftstile: [Name, CSS-Schriftfamilie für Titel]. */
    public const FONTS = [
        'modern' => ['Modern', 'Inter, system-ui, sans-serif'],
        'serif' => ['Klassisch', 'Georgia, "Times New Roman", serif'],
        'playful' => ['Verspielt', '"Comic Sans MS", "Chalkboard SE", "Marker Felt", cursive'],
    ];

    protected $fillable = [
        'organizer_id', 'title', 'host_name', 'description', 'starts_at', 'ends_at', 'venue_name', 'venue_address', 'cover_path',
        'theme', 'font', 'allow_companions', 'max_companions', 'rsvp_deadline', 'allow_items', 'allow_comments',
        'invite_message', 'schedule', 'questions', 'auto_remind_days',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'rsvp_deadline' => 'datetime',
            'allow_companions' => 'boolean', 'allow_items' => 'boolean', 'allow_comments' => 'boolean', 'schedule' => 'array', 'questions' => 'array',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(PartyGuest::class)->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartyItem::class)->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PartyComment::class)->orderByDesc('id');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function palette(): array
    {
        return self::THEMES[$this->theme] ?? self::THEMES['ocean'];
    }

    public function fontFamily(): string
    {
        return (self::FONTS[$this->font] ?? self::FONTS['modern'])[1];
    }

    public function hostLabel(): string
    {
        return $this->host_name ?: $this->organizer->name;
    }

    public function rsvpClosed(): bool
    {
        return $this->rsvp_deadline !== null && $this->rsvp_deadline->isPast();
    }

    public function isOver(): bool
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHours(4))->isPast();
    }

    /** Zählung für die Gastgeber-Übersicht (Personen inkl. Begleitung bei Zusagen). */
    public function counts(): array
    {
        $g = $this->guests;
        $yes = $g->where('response', 'yes');

        return [
            'yes' => $yes->count(),
            'yes_people' => $yes->count() + $yes->sum('companions'),
            'maybe' => $g->where('response', 'maybe')->count(),
            'no' => $g->where('response', 'no')->count(),
            'open' => $g->whereNull('response')->count(),
            'total' => $g->count(),
        ];
    }

    /** Zeitplan als Text («18:00 Apéro» pro Zeile) für das Formular. */
    public function scheduleText(): string
    {
        return collect($this->schedule ?? [])->map(fn ($r) => $r['time'].' '.$r['label'])->implode("\n");
    }

    /** Wahlfragen als Text («Frage | Option, Option» pro Zeile) für das Formular. */
    public function questionsText(): string
    {
        return collect($this->questions ?? [])->map(fn ($q) => $q['label'].($q['options'] ? ' | '.implode(', ', $q['options']) : ''))->implode("\n");
    }

    /** @return array<int, array{time:string,label:string}>|null */
    public static function parseSchedule(?string $text): ?array
    {
        $rows = [];
        foreach (preg_split('/\R/u', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(\d{1,2})[:.](\d{2})\s+(.+)$/u', $line, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
                throw new \InvalidArgumentException($line);
            }
            $rows[] = ['time' => sprintf('%02d:%02d', $m[1], $m[2]), 'label' => mb_substr(trim($m[3]), 0, 120)];
        }
        usort($rows, fn ($a, $b) => strcmp($a['time'], $b['time']));

        return array_slice($rows, 0, 20) ?: null;
    }

    /** @return array<int, array{id:string,label:string,options:array<int,string>}>|null */
    public static function parseQuestions(?string $text): ?array
    {
        $rows = [];
        foreach (preg_split('/\R/u', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $opts] = array_pad(explode('|', $line, 2), 2, '');
            $label = mb_substr(trim($label), 0, 120);
            if ($label === '') {
                continue;
            }
            $options = array_slice(array_values(array_filter(array_map(fn ($o) => mb_substr(trim($o), 0, 60), explode(',', $opts)))), 0, 8);
            $rows[] = ['id' => 'q'.substr(md5(mb_strtolower($label)), 0, 6), 'label' => $label, 'options' => $options];
        }

        return array_slice($rows, 0, 5) ?: null;
    }
}
