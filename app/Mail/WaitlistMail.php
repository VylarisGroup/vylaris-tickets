<?php

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WaitlistMail extends Mailable
{
    public function __construct(public WaitlistEntry $entry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Es sind wieder Tickets für '.$this->entry->event->title.' verfügbar');
    }

    public function content(): Content
    {
        $event = $this->entry->event;

        return new Content(view: 'mail.waitlist', with: [
            'entry' => $this->entry,
            'event' => $event,
            'url' => route('shop.event', [$event->organizer, $event]),
        ]);
    }
}
