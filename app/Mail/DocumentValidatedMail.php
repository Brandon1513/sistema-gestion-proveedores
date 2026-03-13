<?php

namespace App\Mail;

use App\Models\ProviderDocument;
use App\Models\DocumentValidation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $document;
    public $validation;
    public $provider;
    public $status;

    public function __construct(ProviderDocument $document, DocumentValidation $validation)
    {
        $this->document = $document;
        $this->validation = $validation;
        $this->provider = $document->provider;
        $this->status = $validation->action; // 'approved' or 'rejected'
    }

    public function build()
    {
        $subject = $this->status === 'approved' 
            ? 'Documento Aprobado - SGP' 
            : 'Documento Rechazado - SGP';

        return $this->subject($subject)
            ->view('emails.document-validated')
            ->with([
                'providerName' => $this->provider->business_name,
                'documentType' => $this->document->documentType->name,
                'fileName' => $this->document->file_name,
                'status' => $this->status,
                'statusText' => $this->status === 'approved' ? 'APROBADO' : 'RECHAZADO',
                'comments' => $this->validation->comments,
                'validatedBy' => $this->validation->validatedBy->name,
                'validatedAt' => $this->validation->validated_at->format('d/m/Y H:i'),
                'documentsUrl' => config('app.frontend_url') . '/provider/documents',
            ]);
    }
}