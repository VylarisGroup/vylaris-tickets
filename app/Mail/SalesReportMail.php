<?php

namespace App\Mail;

use App\Models\Organizer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SalesReportMail extends Mailable
{
    public function __construct(public Organizer $organizer, public array $rows, public string $periodLabel) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verkaufsreport '.$this->organizer->name.' ('.$this->periodLabel.')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sales-report', with: [
            'organizer' => $this->organizer, 'rows' => $this->rows, 'periodLabel' => $this->periodLabel,
            'url' => route('organizer.stats', $this->organizer),
        ]);
    }
}
