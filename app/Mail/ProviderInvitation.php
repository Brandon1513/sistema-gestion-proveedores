<?php

namespace App\Mail;

use App\Models\ProviderInvitation as ProviderInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $registrationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(ProviderInvitationModel $invitation)
    {
        $this->invitation = $invitation;
        $this->registrationUrl = config('app.frontend_url') . '/register/' . $invitation->token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación para registro de proveedor - SGP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.provider-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}