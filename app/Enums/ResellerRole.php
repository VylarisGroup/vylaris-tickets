<?php

namespace App\Enums;

enum ResellerRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Inhaber',
            self::Admin => 'Administrator',
        };
    }
}
