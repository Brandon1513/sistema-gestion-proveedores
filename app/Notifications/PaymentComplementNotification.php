<?php

namespace App\Notifications;

use App\Models\ProviderPaymentComplement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentComplementNotification extends Notification
{
    use Queueable;

    /** $event: submitted (para CxP) | approved | rejected (para el proveedor) */
    public function __construct(protected ProviderPaymentComplement $complement, protected string $event)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $c = $this->complement->loadMissing('provider:id,business_name', 'payment:id,tran_id');
        $provider = $c->provider->business_name ?? '—';
        $paymentFolio = $c->payment->tran_id ?? '—';
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $copy = match ($this->event) {
            'submitted' => [
                'subject' => "Complemento de pago recibido — {$provider} — Pago {$paymentFolio}",
                'title' => 'Complemento de Pago Recibido',
                'intro' => "El proveedor {$provider} subió el complemento de pago (REP) del pago {$paymentFolio}.",
                'alert' => 'Valida el documento en SGP.',
                'url' => "{$frontend}/finance/account-statement",
                'cta' => '📄 Ver en SGP',
            ],
            'approved' => [
                'subject' => "Tu complemento de pago fue validado",
                'title' => 'Complemento de Pago Validado',
                'intro' => "Tu complemento de pago del pago {$paymentFolio} fue validado correctamente.",
                'alert' => null,
                'url' => "{$frontend}/provider/account-statement",
                'cta' => '📄 Ver mi estado de cuenta',
            ],
            default => [
                'subject' => "Tu complemento de pago fue rechazado",
                'title' => 'Complemento de Pago Rechazado',
                'intro' => "Tu complemento de pago del pago {$paymentFolio} no pudo ser validado.",
                'alert' => null,
                'url' => "{$frontend}/provider/account-statement",
                'cta' => '📄 Ir a mi estado de cuenta',
            ],
        };

        return (new MailMessage)
            ->subject($copy['subject'])
            ->view('emails.payment-complement-event', [
                'title' => $copy['title'],
                'intro' => $copy['intro'],
                'alertText' => $copy['alert'],
                'actionUrl' => $copy['url'],
                'actionLabel' => $copy['cta'],
                'providerName' => $provider,
                'paymentFolio' => $paymentFolio,
                'uuid' => $c->uuid,
                'total' => $c->total,
                'currency' => $c->currency,
                'notes' => $this->event === 'rejected' ? $c->review_notes : null,
            ]);
    }
}