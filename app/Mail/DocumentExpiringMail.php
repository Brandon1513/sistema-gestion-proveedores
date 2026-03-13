<?php

namespace App\Mail;

use App\Models\ProviderDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public $document;
    public $daysUntilExpiry;
    public $urgencyLevel; // 'critical', 'warning', 'notice'

    /**
     * Create a new message instance.
     */
    public function __construct(ProviderDocument $document, int $daysUntilExpiry)
    {
        $this->document = $document;
        $this->daysUntilExpiry = $daysUntilExpiry;
        
        // Determinar nivel de urgencia
        if ($daysUntilExpiry <= 7) {
            $this->urgencyLevel = 'critical';
        } elseif ($daysUntilExpiry <= 15) {
            $this->urgencyLevel = 'warning';
        } else {
            $this->urgencyLevel = 'notice';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $emoji = $this->urgencyLevel === 'critical' ? '🚨' : '⚠️';
        
        return new Envelope(
            subject: $emoji . ' Documento próximo a vencer - ' . $this->document->documentType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.document-expiring',
            with: [
                'document' => $this->document,
                'provider' => $this->document->provider,
                'documentType' => $this->document->documentType,
                'daysUntilExpiry' => $this->daysUntilExpiry,
                'urgencyLevel' => $this->urgencyLevel,
                'appUrl' => config('app.url'),
            ],
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