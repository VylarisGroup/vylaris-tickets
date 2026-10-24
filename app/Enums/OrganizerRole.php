<?php

namespace App\Enums;

enum OrganizerRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Scanner = 'scanner';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Inhaber',
            self::Manager => 'Verwalter',
            self::Cashier => 'Kasse',
            self::Scanner => 'Einlass',
        };
    }

    /** Rollen, die Events, Tickets und Team verwalten dürfen. */
    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canSell(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Cashier], true);
    }

    public function canScan(): bool
    {
        return true;
    }
}
