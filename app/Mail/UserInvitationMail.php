<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitación de acceso al Panel Administrativo · Innovamente');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user-invitation');
    }

    public function attachments(): array
    {
        return [];
    }
}
