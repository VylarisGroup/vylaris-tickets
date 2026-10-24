<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(public Invitation $invitation, public string $token) {}

    public function envelope(): Envelope
    {
        $reseller = $this->invitation->organizer->reseller;
        $brand = $reseller?->displayName() ?? config('tickets.name');

        return new Envelope(
            subject: 'Einladung zu '.$brand,
            replyTo: $reseller?->support_email ? [new Address($reseller->support_email)] : [],
        );
    }

    public function content(): Content
    {
        $organizer = $this->invitation->organizer;

        return new Content(view: 'mail.invitation', with: [
            'invitation' => $this->invitation,
            'organizer' => $organizer,
            'reseller' => $organizer->reseller,
            'url' => route('invitation.show', $this->token),
        ]);
    }
}
