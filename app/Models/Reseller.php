<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    protected $fillable = [
        'name', 'slug', 'domain', 'brand_name', 'brand_color', 'logo_path', 'support_email',
        'allow_registration', 'max_organizers', 'max_events_per_organizer', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_registration' => 'boolean',
            'is_active' => 'boolean',
            'max_organizers' => 'integer',
            'max_events_per_organizer' => 'integer',
        ];
    }

    public function organizers(): HasMany
    {
        return $this->hasMany(Organizer::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(ResellerDomain::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function displayName(): string
    {
        return $this->brand_name ?: $this->name;
    }

    public function color(): string
    {
        return $this->brand_color ?: config('tickets.brand_color');
    }

    public function canAddOrganizer(): bool
    {
        return $this->max_organizers === null || $this->organizers()->count() < $this->max_organizers;
    }
}
