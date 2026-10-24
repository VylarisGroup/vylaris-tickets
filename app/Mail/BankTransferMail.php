<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BankTransferMail extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zahlungsangaben für deine Bestellung '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.bank-transfer', with: [
            'order' => $this->order,
            'event' => $this->order->event,
            'organizer' => $this->order->event->organizer,
            'url' => route('order.show', $this->order->access_token),
        ]);
    }
}
