<?php

namespace App\Support;

class Money
{
    /** "25", "25.5", "25,50", "1'250.00" → Rappen/Cent. Gibt null bei ungültiger Eingabe zurück. */
    public static function toCents(string|int|float|null $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        $clean = str_replace(["'", ' ', '’'], '', (string) $input);
        $clean = str_replace(',', '.', $clean);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }

    public static function format(int $cents, string $currency = 'CHF'): string
    {
        return $currency.' '.number_format($cents / 100, 2, '.', "'");
    }

    /** Für Formularfelder: 2500 → "25.00" */
    public static function input(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
