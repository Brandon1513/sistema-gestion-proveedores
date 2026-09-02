<?php

namespace App\Notifications;

use App\Models\ProviderCreditNoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Provider;

class CreditNoteResponseReceivedNotification extends Notification
{
    use Queueable;

    protected ProviderCreditNoteRequest $creditNoteRequest;

    public function __construct(ProviderCreditNoteRequest $creditNoteRequest)
    {
        $this->creditNoteRequest = $creditNoteRequest;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $tipoLabels = [
            'faltante' => 'Producto Faltante',
            'devolucion' => 'Devolución',
            'rechazo' => 'Rechazo',
        ];
        $tipoLabel = $tipoLabels[$this->creditNoteRequest->type] ?? $this->creditNoteRequest->type;

        return (new MailMessage)
            ->subject("{$this->creditNoteRequest->provider->business_name} subió su CFDI de nota de crédito")
            ->view('emails.credit-note-response-received', [
                'providerName' => $this->creditNoteRequest->provider->business_name,
                'tipoLabel' => $tipoLabel,
                'descripcion' => $this->creditNoteRequest->description,
                'facturaFolio' => $this->creditNoteRequest->relatedInvoice?->tran_id,
                'actionUrl' => url('/finance/account-statement'),
            ]);
    }
}