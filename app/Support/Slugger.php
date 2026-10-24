<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slugger
{
    /** Erzeugt einen eindeutigen Slug für die angegebene Modellklasse. */
    public static function unique(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'eintrag';
        $slug = $base;
        $i = 2;

        while ($modelClass::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
