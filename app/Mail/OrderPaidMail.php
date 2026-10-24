<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\TicketPdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderPaidMail extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deine Tickets für '.$this->order->event->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-paid', with: [
            'order' => $this->order,
            'event' => $this->order->event,
            'url' => route('order.show', $this->order->access_token),
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(TicketPdf::class)->render($this->order), 'tickets-'.$this->order->number.'.pdf')->withMime('application/pdf'),
        ];
    }
}
