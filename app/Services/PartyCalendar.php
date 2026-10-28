<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyGuest;
use Illuminate\Support\Str;

/** Kalendereintrag (.ics) einer Einladung. */
class PartyCalendar
{
    public function ics(Party $party, PartyGuest $guest): string
    {
        $end = $party->ends_at ?? $party->starts_at->copy()->addHours(3);
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Vylaris Tickets//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:party-'.$party->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$party->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->esc($party->title),
        ];
        if ($party->venue_name || $party->venue_address) {
            $lines[] = 'LOCATION:'.$this->esc(trim($party->venue_name.', '.$party->venue_address, ', '));
        }
        $lines[] = 'DESCRIPTION:'.$this->esc(trim(Str::limit(strip_tags((string) $party->description), 300)."\n".$guest->url()));
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    private function esc(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $ch) {
            if (strlen($current.$ch) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ").$current;
                $current = '';
            }
            $current .= $ch;
        }

        return $out."\r\n ".$current;
    }
}
