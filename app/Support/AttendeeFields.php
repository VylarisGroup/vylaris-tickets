<?php

namespace App\Support;

use App\Exceptions\CheckoutException;
use App\Models\Event;

/** Angaben zur Person pro Ticket: welche Felder es gibt und wie sie geprüft werden. Der Name ist immer Pflicht. */
class AttendeeFields
{
    /** Wählbare Zusatzfelder: Schlüssel => [Bezeichnung, Spalte am Ticket]. */
    public const OPTIONAL = [
        'birthdate' => ['Geburtsdatum', 'holder_birthdate'],
        'city' => ['Wohnort', 'holder_city'],
        'email' => ['E-Mail', 'holder_email'],
        'phone' => ['Telefon', 'holder_phone'],
    ];

    public static function keys(): array
    {
        return array_keys(self::OPTIONAL);
    }

    public static function label(string $key): string
    {
        return $key === 'name' ? 'Name' : self::OPTIONAL[$key][0];
    }

    /**
     * Prüft die Angaben einer Person und gibt die Ticket-Spalten zurück.
     *
     * @throws CheckoutException
     */
    public static function clean(Event $event, array $raw, string $who): array
    {
        $out = [];
        $name = trim((string) ($raw['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 120) {
            throw new CheckoutException("{$who}: Bitte den Namen angeben.");
        }
        $out['holder_name'] = $name;

        foreach ($event->attendeeFields() as $key) {
            $value = trim((string) ($raw[$key] ?? ''));
            $label = self::label($key);

            if ($value === '') {
                throw new CheckoutException("{$who}: Bitte {$label} angeben.");
            }

            $out[self::OPTIONAL[$key][1]] = match ($key) {
                'birthdate' => self::birthdate($value, $who),
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 190 ? $value : throw new CheckoutException("{$who}: Bitte eine gültige E-Mail-Adresse angeben."),
                'phone' => preg_match('/^[0-9+()\/\-. ]{6,40}$/', $value) ? $value : throw new CheckoutException("{$who}: Bitte eine gültige Telefonnummer angeben."),
                'city' => mb_strlen($value) <= 100 ? $value : throw new CheckoutException("{$who}: Der Wohnort ist zu lang."),
            };
        }

        return $out;
    }

    /** Akzeptiert «1990-03-12» (Datumsfeld) und «12.03.1990» (Handeingabe). */
    private static function birthdate(string $value, string $who): string
    {
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            // DateTimeImmutable liefert bei falschem Format false statt einer Exception (Eingabe kommt vom Kunden).
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value && $date <= new \DateTimeImmutable('today') && (int) $date->format('Y') >= 1900) {
                return $date->format('Y-m-d');
            }
        }

        throw new CheckoutException("{$who}: Bitte ein gültiges Geburtsdatum angeben.");
    }
}
