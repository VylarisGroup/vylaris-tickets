<?php

namespace App\Enums;

enum EventType: string
{
    case Onsite = 'onsite';
    case Digital = 'digital';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Vor Ort',
            self::Digital => 'Digital',
            self::Hybrid => 'Hybrid (vor Ort und digital)',
        };
    }

    public function hasVenue(): bool
    {
        return $this !== self::Digital;
    }

    public function isOnline(): bool
    {
        return $this !== self::Onsite;
    }
}
