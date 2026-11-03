<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envía los correos de Laravel con Microsoft Graph desde el buzón compartido
 * del SGP (sgp@dasavena.com). Todos los Mail::to(), Mailables y notificaciones
 * lo usan sin cambiar nada (MAIL_MAILER=microsoft-graph).
 *
 * A diferencia de la intranet, el SGP envía SIEMPRE desde un solo buzón: el
 * "from" que traiga el correo se ignora, para no mandar a nombre de nadie más.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
        private string $remitente,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $html = $email->getHtmlBody();
        if (is_resource($html)) {
            $html = stream_get_contents($html);
        }

        $texto = $email->getTextBody();
        if (is_resource($texto)) {
            $texto = stream_get_contents($texto);
        }

        $payload = [
            'message' => array_filter([
                'subject'       => $email->getSubject() ?? '',
                'body'          => $html !== null
                    ? ['contentType' => 'HTML', 'content' => $html]
                    : ['contentType' => 'Text', 'content' => (string) $texto],
                'toRecipients'  => $this->direcciones($email->getTo()),
                'ccRecipients'  => $this->direcciones($email->getCc()),
                'bccRecipients' => $this->direcciones($email->getBcc()),
                'replyTo'       => $this->direcciones($email->getReplyTo()),
                'attachments'   => $this->adjuntos($email->getAttachments()),
            ]),
            // No llenar "Elementos enviados" del buzón con cada aviso automático
            'saveToSentItems' => false,
        ];

        $respuesta = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(30)
            ->post("https://graph.microsoft.com/v1.0/users/{$this->remitente}/sendMail", $payload);

        if ($respuesta->failed()) {
            // Si el token venció antes de tiempo, la próxima vez se pide uno nuevo
            if ($respuesta->status() === 401) {
                Cache::forget($this->claveToken());
            }

            throw new TransportException('Microsoft Graph respondió ' . $respuesta->status() . ': ' . $respuesta->body());
        }
    }

    /**
     * Token de la aplicación (flujo "client credentials"). Dura una hora; se guarda 50 minutos.
     */
    private function token(): string
    {
        return Cache::remember($this->claveToken(), now()->addMinutes(50), function () {
            return Http::asForm()
                ->timeout(15)
                ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ])
                ->throw()
                ->json('access_token');
        });
    }

    private function claveToken(): string
    {
        return 'microsoft_graph_token_' . $this->clientId;
    }

    /** @param Address[] $direcciones */
    private function direcciones(array $direcciones): array
    {
        return array_map(fn (Address $d) => [
            'emailAddress' => array_filter(['address' => $d->getAddress(), 'name' => $d->getName()]),
        ], $direcciones);
    }

    private function adjuntos(array $partes): array
    {
        return array_map(fn ($parte) => array_filter([
            '@odata.type'  => '#microsoft.graph.fileAttachment',
            'name'         => $parte->getFilename() ?? 'adjunto',
            'contentType'  => $parte->getMediaType() . '/' . $parte->getMediaSubtype(),
            'contentBytes' => base64_encode($parte->getBody()),
            // Imágenes dentro del correo (como el logo): se muestran en el cuerpo, no como adjunto
            'isInline'     => $parte->getDisposition() === 'inline',
            'contentId'    => $parte->hasContentId() ? $parte->getContentId() : null,
        ], fn ($valor) => $valor !== null), $partes);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}