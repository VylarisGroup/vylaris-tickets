<?php

namespace App\Models;

use App\Enums\OrganizerRole;
use App\Enums\ResellerRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'push_prefs' => 'array',
        ];
    }

    /** Push-Benachrichtigungsarten: Schlüssel => [Titel, Beschreibung, nur für Plattform-Admins]. */
    public const PUSH_CATEGORIES = [
        'order_paid' => ['Neue Bestellung', 'Eine Bestellung wurde bezahlt (online, ohne Abendkasse).', false],
        'order_pending' => ['Überweisung wartet', 'Eine Bestellung per Banküberweisung wartet auf den Zahlungseingang.', false],
        'sold_out' => ['Ausverkauft', 'Eine Ticketart ist ausverkauft.', false],
        'waitlist' => ['Neue Warteliste-Anmeldung', 'Jemand hat sich auf die Warteliste gesetzt.', false],
        'report' => ['Neue Meldung', 'Ein Event wurde gemeldet und wartet auf Prüfung.', true],
        'update' => ['Neue Version', 'Es ist eine neue Plattform-Version verfügbar.', true],
    ];

    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Standardmässig sind alle Arten aktiv, bis der Nutzer sie abschaltet. */
    public function wantsPush(string $category): bool
    {
        return (bool) (($this->push_prefs ?? [])[$category] ?? true);
    }

    public function resellers(): BelongsToMany
    {
        return $this->belongsToMany(Reseller::class)->withPivot('role')->withTimestamps();
    }

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class)->withPivot('role')->withTimestamps();
    }

    public function resellerRole(Reseller $reseller): ?ResellerRole
    {
        $role = $this->resellers()->whereKey($reseller->getKey())->first()?->pivot->role;

        return $role ? ResellerRole::from($role) : null;
    }

    public function organizerRole(Organizer $organizer): ?OrganizerRole
    {
        $role = $this->organizers()->whereKey($organizer->getKey())->first()?->pivot->role;

        return $role ? OrganizerRole::from($role) : null;
    }

    /** Darf der Nutzer den Veranstalter verwalten (Plattform-Admin, Reseller-Mitglied oder Inhaber/Verwalter)? */
    public function canManageOrganizer(Organizer $organizer): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        if ($organizer->reseller_id && $this->resellerRole($organizer->reseller)) {
            return true;
        }

        return (bool) $this->organizerRole($organizer)?->canManage();
    }

    /** Darf der Nutzer den Veranstalter überhaupt sehen? */
    public function canAccessOrganizer(Organizer $organizer): bool
    {
        // Gesperrte Veranstalter sind nur für Plattform-Admins und deren Reseller zugänglich.
        if (! $organizer->is_active) {
            return $this->is_platform_admin
                || ($organizer->reseller_id && $this->resellerRole($organizer->reseller) !== null);
        }

        return $this->canManageOrganizer($organizer) || $this->organizerRole($organizer) !== null;
    }

    public function canManageReseller(Reseller $reseller): bool
    {
        return $this->is_platform_admin || $this->resellerRole($reseller) !== null;
    }

    /** Darf an der Abendkasse verkaufen (Inhaber, Verwalter, Kasse; Plattform-Admin/Reseller ebenso). */
    public function canSellAt(Organizer $organizer): bool
    {
        return $this->canManageOrganizer($organizer) || (bool) $this->organizerRole($organizer)?->canSell();
    }

    /** Darf am Einlass scannen (alle Teamrollen). */
    public function canScanAt(Organizer $organizer): bool
    {
        return $this->canManageOrganizer($organizer) || (bool) $this->organizerRole($organizer)?->canScan();
    }
}
