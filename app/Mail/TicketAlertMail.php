<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketAlertMail extends Mailable
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $subjectLine,
        public string $headline,
        public string $messageLine,
        public array $details = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-alert',
            text: 'emails.ticket-alert-text',
        );
    }
}
