<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\SeatingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'organizer_id', 'title', 'slug', 'description', 'type', 'category', 'cover_path', 'city', 'status', 'venue_name', 'venue_address',
        'online_url', 'online_instructions', 'starts_at', 'ends_at', 'doors_open_at', 'sales_start_at',
        'sales_end_at', 'currency', 'door_sales_enabled', 'seating_mode', 'is_blocked', 'blocked_reason',
    ];

    // Zugangslink und Hinweise gehen nur an Ticketinhaber, nie in öffentliche Ausgaben.
    protected $hidden = ['online_url', 'online_instructions'];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'doors_open_at' => 'datetime',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'door_sales_enabled' => 'boolean',
            'seating_mode' => SeatingMode::class,
            'is_blocked' => 'boolean',
        ];
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover_path) : null;
    }

    public function categoryLabel(): ?string
    {
        return config('tickets.categories')[$this->category] ?? null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(ScanPairing::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SeatSection::class)->orderBy('sort')->orderBy('id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    /** Ist der Vorverkauf zum angegebenen Zeitpunkt offen? */
    public function isOnSale(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        return $this->status === EventStatus::Published
            && ! $this->is_blocked
            && (! $this->sales_start_at || $this->sales_start_at->lte($at))
            && (! $this->sales_end_at || $this->sales_end_at->gte($at))
            && $this->starts_at->gte($at->copy()->subDay());
    }
}
