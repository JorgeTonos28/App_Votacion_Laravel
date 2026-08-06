<?php

namespace App\Mail;

use App\Models\Juror;
use App\Models\VotingEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class JurorAccessMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Juror $juror,
        public readonly VotingEvent $event,
        public readonly string $code,
        public readonly string $accessUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Acceso de jurado · {$this->event->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.juror-access');
    }

    public function attachments(): array
    {
        return [];
    }
}
