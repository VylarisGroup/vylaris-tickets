<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Erinnerung an Ticketkäufer kurz vor dem Event. */
class EventReminderMail extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $event = $this->order->event;
        $replyTo = $event->organizer->contact_email;

        return new Envelope(
            subject: 'Erinnerung: '.$event->title.' – '.$event->starts_at->translatedFormat('d.m.Y, H:i').' Uhr',
            replyTo: $replyTo ? [new \Illuminate\Mail\Mailables\Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-reminder', with: [
            'order' => $this->order,
            'event' => $this->order->event,
            'url' => route('order.show', $this->order->access_token),
        ]);
    }
}
