<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'organizer_id', 'title', 'slug', 'description', 'type', 'status', 'venue_name', 'venue_address',
        'online_url', 'online_instructions', 'starts_at', 'ends_at', 'doors_open_at', 'sales_start_at',
        'sales_end_at', 'currency', 'door_sales_enabled',
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
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
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
            && (! $this->sales_start_at || $this->sales_start_at->lte($at))
            && (! $this->sales_end_at || $this->sales_end_at->gte($at))
            && $this->starts_at->gte($at->copy()->subDay());
    }
}
