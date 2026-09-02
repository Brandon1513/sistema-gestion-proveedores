<?php

namespace App\Notifications;

use App\Models\ProviderCreditNoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Provider;

class CreditNoteRequestNotification extends Notification
{
    use Queueable;

    protected ProviderCreditNoteRequest $creditNoteRequest;
    protected string $accion;

    public function __construct(ProviderCreditNoteRequest $creditNoteRequest, string $accion)
    {
        $this->creditNoteRequest = $creditNoteRequest;
        $this->accion = $accion;
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

        $statusLabels = [
            'pending' => 'Pendiente de revisión',
            'in_review' => 'En revisión',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'sent_to_netsuite' => 'Procesada en NetSuite',
        ];
        $statusLabel = $statusLabels[$this->creditNoteRequest->status] ?? $this->creditNoteRequest->status;

        return (new MailMessage)
            ->subject($this->accion === 'creada'
                ? "Nueva nota de crédito registrada — {$tipoLabel}"
                : "Actualización de tu nota de crédito — {$statusLabel}")
            ->view('emails.credit-note-request', [
                'providerName' => $this->creditNoteRequest->provider->business_name,
                'accion' => $this->accion,
                'tipoLabel' => $tipoLabel,
                'statusLabel' => $statusLabel,
                'descripcion' => $this->creditNoteRequest->description,
                'montoSolicitado' => $this->creditNoteRequest->amount_requested,
                'facturaFolio' => $this->creditNoteRequest->relatedInvoice?->tran_id,
                'actionUrl' => url('/provider/account-statement'),
            ]);
    }
}