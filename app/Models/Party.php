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
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'rsvp_deadline' => 'datetime',
            'allow_companions' => 'boolean', 'allow_items' => 'boolean', 'allow_comments' => 'boolean',
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
}
