<?php

namespace App\Jobs;

use App\Models\ProviderDocument;
use App\Mail\DocumentExpiringMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendExpiringDocumentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $document;
    public $daysUntilExpiry;
    public $providerEmail;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(ProviderDocument $document, int $daysUntilExpiry, string $providerEmail)
    {
        $this->document = $document;
        $this->daysUntilExpiry = $daysUntilExpiry;
        $this->providerEmail = $providerEmail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->providerEmail)->send(
                new DocumentExpiringMail($this->document, $this->daysUntilExpiry)
            );

            Log::info('Email de vencimiento enviado', [
                'document_id' => $this->document->id,
                'provider_id' => $this->document->provider_id,
                'days_until_expiry' => $this->daysUntilExpiry,
                'email' => $this->providerEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al enviar email de vencimiento', [
                'document_id' => $this->document->id,
                'provider_id' => $this->document->provider_id,
                'email' => $this->providerEmail,
                'error' => $e->getMessage(),
            ]);

            // Re-lanzar la excepción para que el job se reintente
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de notificación de vencimiento falló después de todos los intentos', [
            'document_id' => $this->document->id,
            'provider_id' => $this->document->provider_id,
            'email' => $this->providerEmail,
            'error' => $exception->getMessage(),
        ]);

        // Aquí podrías notificar al equipo interno sobre el fallo
        // Por ejemplo, enviar un email al administrador
    }
}