<?php

namespace App\Mail;

use App\Models\Party;
use App\Models\PartyGuest;
use App\Services\PartyCalendar;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Bestätigung der Antwort an den Gast, mit Kalender-Datei und Link zum Ändern. */
class PartyResponseMail extends Mailable
{
    public function __construct(public Party $party, public PartyGuest $guest) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->party->organizer->contact_email;

        return new Envelope(subject: 'Deine Antwort: '.$this->party->title, replyTo: $replyTo ? [new Address($replyTo)] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.party-response', with: ['party' => $this->party, 'guest' => $this->guest, 'url' => $this->guest->url()]);
    }

    public function attachments(): array
    {
        if (! in_array($this->guest->response, ['yes', 'maybe'], true)) {
            return [];
        }

        return [Attachment::fromData(fn () => app(PartyCalendar::class)->ics($this->party, $this->guest), 'einladung.ics')->withMime('text/calendar')];
    }
}
