<?php

namespace App\Mail;

use App\Models\Party;
use App\Models\PartyGuest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Einladung (oder Erinnerung) mit dem persönlichen Link des Gastes. */
class PartyInvitationMail extends Mailable
{
    public function __construct(public Party $party, public PartyGuest $guest, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->party->organizer->contact_email;

        return new Envelope(
            subject: ($this->reminder ? 'Erinnerung: ' : 'Du bist eingeladen: ').$this->party->title,
            replyTo: $replyTo ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.party-invitation', with: [
            'party' => $this->party, 'guest' => $this->guest, 'reminder' => $this->reminder, 'url' => $this->guest->url(),
        ]);
    }
}
