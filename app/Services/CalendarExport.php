<?php

namespace App\Services;

use App\Models\Event;

/** Kalendereinträge: iCalendar-Datei (.ics) und Google-Kalender-Link. */
class CalendarExport
{
    public function ics(Event $event): string
    {
        $event->loadMissing('organizer');
        $end = $event->ends_at ?? $event->starts_at->copy()->addHours(2);
        $url = route('shop.event', [$event->organizer, $event]);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Vylaris Tickets//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url($url, PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$event->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->esc($event->title),
        ];

        if ($event->type->hasVenue() && $event->venue_name) {
            $lines[] = 'LOCATION:'.$this->esc(trim($event->venue_name.', '.$event->venue_address, ', '));
        }

        $lines[] = 'DESCRIPTION:'.$this->esc(trim(\Illuminate\Support\Str::limit(strip_tags((string) $event->description), 300)."\n".$url));
        $lines[] = 'URL:'.$url;
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    public function googleUrl(Event $event): string
    {
        $end = $event->ends_at ?? $event->starts_at->copy()->addHours(2);

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event->title,
            'dates' => $event->starts_at->copy()->utc()->format('Ymd\THis\Z').'/'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'location' => $event->type->hasVenue() ? trim($event->venue_name.', '.$event->venue_address, ', ') : '',
            'details' => route('shop.event', [$event->organizer, $event]),
        ]);
    }

    private function esc(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Zeilen auf 75 Oktette umbrechen (RFC 5545), ohne Mehrbyte-Zeichen zu zerteilen. */
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
