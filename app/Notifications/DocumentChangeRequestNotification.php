<?php

namespace App\Notifications;

use App\Models\DocumentChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DocumentChangeRequestNotification extends Notification
{
    use Queueable;

    /** $event: submitted (para CxP) | attended | rejected (para el proveedor) */
    public function __construct(protected DocumentChangeRequest $changeRequest, protected string $event)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $cr = $this->changeRequest->loadMissing('provider', 'documentType');
        $cr->loadCount('images');

        $provider = $cr->provider->business_name ?? '—';
        $document = $cr->documentType->name ?? 'Convenio comercial';
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $copy = match ($this->event) {
            'submitted' => [
                'subject' => "Solicitud de cambios al convenio — {$provider}",
                'title'   => 'Solicitud de Cambios al Convenio',
                'intro'   => "El proveedor {$provider} solicitó cambios al documento \"{$document}\" antes de firmarlo.",
                'alert'   => 'Revisa la solicitud y sube la versión ajustada, o márcala como no procedente.',
                'url'     => "{$frontend}/change-requests?id={$cr->id}",
                'cta'     => '📝 Revisar solicitud',
            ],
            'attended' => [
                'subject' => "Tu convenio fue ajustado — {$document}",
                'title'   => 'Convenio Ajustado',
                'intro'   => "Revisamos los cambios que solicitaste al documento \"{$document}\" y ya está lista tu versión ajustada.",
                'alert'   => 'Descárgala desde Cargar Documentos, fírmala y súbela al portal.',
                'url'     => "{$frontend}/provider/documents",
                'cta'     => '📄 Ir al portal',
            ],
            default => [
                'subject' => "Respuesta a tu solicitud de cambios — {$document}",
                'title'   => 'Solicitud de Cambios No Procedente',
                'intro'   => "Revisamos los cambios que solicitaste al documento \"{$document}\" y no es posible aplicarlos.",
                'alert'   => 'Puedes continuar con la plantilla vigente o enviar una nueva solicitud desde el portal.',
                'url'     => "{$frontend}/provider/documents",
                'cta'     => '📄 Ir al portal',
            ],
        };

        $isSubmitted = $this->event === 'submitted';

        return (new MailMessage)
            ->subject($copy['subject'])
            ->view('emails.document-change-request-event', [
                'title'        => $copy['title'],
                'intro'        => $copy['intro'],
                'alertText'    => $copy['alert'],
                'actionUrl'    => $copy['url'],
                'actionLabel'  => $copy['cta'],
                'providerName' => $provider,
                'documentName' => $document,
                'requestedAt'  => $cr->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                // Solo CxP ve el texto del proveedor y el número de imágenes
                'description'  => $isSubmitted ? Str::limit($cr->description, 1200) : null,
                'imagesCount'  => $isSubmitted ? (int) $cr->images_count : null,
                // Solo el proveedor ve la respuesta
                'notes'        => $isSubmitted ? null : $cr->response_notes,
                'notesLabel'   => $this->event === 'rejected' ? 'Motivo' : 'Comentarios',
            ]);
    }
}