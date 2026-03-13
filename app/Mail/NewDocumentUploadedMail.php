<?php

namespace App\Mail;

use App\Models\ProviderDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewDocumentUploadedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $document;
    public $provider;

    public function __construct(ProviderDocument $document)
    {
        $this->document = $document;
        $this->provider = $document->provider;
    }

    public function build()
    {
        return $this->subject('Nuevo Documento Pendiente de Validación - SGP')
            ->view('emails.new-document-uploaded')
            ->with([
                'providerName' => $this->provider->business_name,
                'providerRFC' => $this->provider->rfc,
                'documentType' => $this->document->documentType->name,
                'fileName' => $this->document->file_name,
                'uploadedAt' => $this->document->created_at->format('d/m/Y H:i'),
                'validationUrl' => config('app.frontend_url') . '/documents/validation',
            ]);
    }
}