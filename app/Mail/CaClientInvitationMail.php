<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaClientInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $rawToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->invitation->tenant->name} wants to manage your business on Accobot",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ca_client_invitation',
            with: ['rawToken' => $this->rawToken],
        );
    }
}
