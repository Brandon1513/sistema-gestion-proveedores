<?php

namespace App\Notifications;

use App\Models\ProviderInvoiceSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSubmissionNotification extends Notification
{
    use Queueable;

    /** $event: submitted (para CxP) | captured | rejected (para el proveedor) */
    public function __construct(protected ProviderInvoiceSubmission $submission, protected string $event)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $s = $this->submission->loadMissing('provider:id,business_name');
        $provider = $s->provider->business_name ?? '—';
        $folio = trim(($s->serie ?? '') . ' ' . ($s->folio ?? '')) ?: '—';
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $copy = match ($this->event) {
            'submitted' => [
                'subject' => "Nueva factura subida — {$provider} — {$folio}",
                'title' => 'Nueva Factura Recibida',
                'intro' => "El proveedor {$provider} subió una factura (PDF y XML) para captura en NetSuite.",
                'alert' => 'Captura la factura en NetSuite y márcala como capturada en SGP.',
                'url' => "{$frontend}/finance/account-statement",
                'cta' => '📄 Ver factura en SGP',
            ],
            'captured' => [
                'subject' => "Tu factura {$folio} fue capturada",
                'title' => 'Factura Capturada',
                'intro' => 'Tu factura fue recibida y capturada en nuestro sistema. Entra al proceso de pago.',
                'alert' => null,
                'url' => "{$frontend}/provider/account-statement",
                'cta' => '📄 Ver mi estado de cuenta',
            ],
            default => [
                'subject' => "Tu factura {$folio} fue rechazada",
                'title' => 'Factura Rechazada',
                'intro' => 'Tu factura no pudo ser procesada. Revisa el motivo, corrígela y vuelve a subirla.',
                'alert' => null,
                'url' => "{$frontend}/provider/account-statement",
                'cta' => '📄 Ir a mi estado de cuenta',
            ],
            'cancelled' => [
            'subject' => "Tu factura {$folio} fue cancelada",
            'title' => 'Factura Cancelada',
            'intro' => 'Tu factura fue cancelada en nuestro sistema. Esto suele pasar cuando el CFDI fue cancelado y re-timbrado ante el SAT. Si tienes la versión corregida, súbela como una nueva factura.',
            'alert' => 'Si el CFDI fue re-timbrado, sube la nueva versión desde tu portal.',
            'url' => "{$frontend}/provider/account-statement",
            'cta' => '📄 Subir factura corregida',
            ],
        };

        return (new MailMessage)
            ->subject($copy['subject'])
            ->view('emails.invoice-submission-event', [
                'title' => $copy['title'],
                'intro' => $copy['intro'],
                'alertText' => $copy['alert'],
                'actionUrl' => $copy['url'],
                'actionLabel' => $copy['cta'],
                'providerName' => $provider,
                'folio' => $folio,
                'uuid' => $s->uuid,
                'total' => $s->total,
                'currency' => $s->currency,
                'paymentMethodLabel' => match ($s->payment_method) {
                    'PUE' => 'Pago en una sola exhibición (PUE)',
                    'PPD' => 'Pago en parcialidades o diferido (PPD)',
                    default => '—',
                },
                'notes' => $this->event === 'rejected' ? $s->review_notes : null,
            ]);
    }
}