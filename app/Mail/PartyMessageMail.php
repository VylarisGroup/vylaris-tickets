<?php

namespace App\Mail;

use App\Models\Party;
use App\Models\PartyGuest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Rundmail des Gastgebers an die Gäste einer Einladung. */
class PartyMessageMail extends Mailable
{
    public function __construct(public Party $party, public PartyGuest $guest, public string $mailSubject, public string $body) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->party->organizer->contact_email;

        return new Envelope(subject: $this->mailSubject.' · '.$this->party->title, replyTo: $replyTo ? [new Address($replyTo)] : []);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.party-message', with: ['party' => $this->party, 'guest' => $this->guest, 'body' => $this->body, 'url' => $this->guest->url()]);
    }
}
