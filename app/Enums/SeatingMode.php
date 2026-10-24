<?php

namespace App\Enums;

enum SeatingMode: string
{
    case None = 'none';
    case Grid = 'grid';
    case Map = 'map';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Ohne Platzwahl (Kontingente)',
            self::Grid => 'Raster mit Reihen und Plätzen',
            self::Map => 'Hallenplan-Editor (Sitze, Tische, Ebenen)',
        };
    }

    public function isSeated(): bool
    {
        return $this !== self::None;
    }
}
