<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Nachricht des Veranstalters an alle Ticketkäufer eines Events. */
class EventMessageMail extends Mailable
{
    public function __construct(public Event $event, public string $subjectLine, public string $body, public ?string $replyAddress = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->replyAddress ? [new \Illuminate\Mail\Mailables\Address($this->replyAddress)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-message', with: [
            'event' => $this->event, 'body' => $this->body, 'url' => route('shop.event', [$this->event->organizer, $this->event]),
        ]);
    }
}
