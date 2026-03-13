<?php

namespace App\Console\Commands;

use App\Models\ProviderDocument;
use App\Mail\DocumentExpiringMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CheckExpiringDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:check-expiring 
                            {--days=* : Días específicos para verificar (ej: --days=7 --days=15)}
                            {--send-emails : Enviar emails a los proveedores}
                            {--dry-run : Ejecutar sin enviar emails (solo mostrar)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica documentos próximos a vencer y envía notificaciones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando documentos próximos a vencer...');
        $this->newLine();

        // Días a verificar (por defecto: 60, 30, 15, 7)
        $daysToCheck = $this->option('days') ?: [60, 30, 15, 7];
        
        // Convertir a enteros si vienen como strings
        $daysToCheck = array_map('intval', $daysToCheck);
        
        $sendEmails = $this->option('send-emails');
        $dryRun = $this->option('dry-run');

        $today = Carbon::today();
        $totalDocuments = 0;
        $totalEmailsSent = 0;
        $emailsFailed = 0;

        foreach ($daysToCheck as $days) {
            // Asegurar que $days es un entero
            $days = (int) $days;
            
            $targetDate = $today->copy()->addDays($days);
            
            // Buscar documentos que vencen exactamente en N días
            $documents = ProviderDocument::with(['provider.providerType', 'documentType'])
                ->where('status', 'approved') // Solo documentos aprobados
                ->whereDate('expiry_date', $targetDate->toDateString())
                ->get();

            if ($documents->isEmpty()) {
                $this->line("  ⚪ {$days} días: No hay documentos");
                continue;
            }

            $totalDocuments += $documents->count();

            // Mostrar información
            if ($days <= 7) {
                $this->error("  🚨 {$days} días: {$documents->count()} documentos URGENTES");
            } elseif ($days <= 15) {
                $this->warn("  ⚠️  {$days} días: {$documents->count()} documentos");
            } else {
                $this->info("  📅 {$days} días: {$documents->count()} documentos");
            }

            // Mostrar detalles de cada documento
            foreach ($documents as $document) {
                $providerName = $document->provider->business_name ?? 'Sin nombre';
                $documentType = $document->documentType->name ?? 'Sin tipo';
                $providerEmail = $this->getProviderEmail($document);

                $this->line("     - {$providerName} | {$documentType}");
                
                if (!$providerEmail) {
                    $this->warn("       ⚠️  Sin email registrado");
                    continue;
                }

                $this->line("       📧 Email: {$providerEmail}");

                // Enviar email si está habilitado
                if ($sendEmails && !$dryRun) {
                    try {
                        Mail::to($providerEmail)->send(
                            new DocumentExpiringMail($document, $days)
                        );
                        $this->line("       ✓ Email enviado");
                        $totalEmailsSent++;
                    } catch (\Exception $e) {
                        $this->error("       ✗ Error al enviar: {$e->getMessage()}");
                        $emailsFailed++;
                    }
                } elseif ($dryRun) {
                    $this->line("       [DRY RUN] Email NO enviado");
                }
            }

            $this->newLine();
        }

        // Resumen
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("📊 RESUMEN");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("  Total documentos encontrados: {$totalDocuments}");
        
        if ($sendEmails && !$dryRun) {
            $this->line("  Emails enviados exitosamente: {$totalEmailsSent}");
            if ($emailsFailed > 0) {
                $this->error("  Emails fallidos: {$emailsFailed}");
            }
        } elseif ($dryRun) {
            $this->warn("  [DRY RUN] No se enviaron emails");
        } else {
            $this->warn("  No se enviaron emails (use --send-emails para enviar)");
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Verificar documentos ya vencidos
        $this->checkExpiredDocuments();

        return Command::SUCCESS;
    }

    /**
     * Verificar documentos ya vencidos
     */
    protected function checkExpiredDocuments()
    {
        $expiredDocuments = ProviderDocument::with(['provider', 'documentType'])
            ->where('status', 'approved')
            ->whereDate('expiry_date', '<', Carbon::today())
            ->get();

        if ($expiredDocuments->isEmpty()) {
            $this->info('✅ No hay documentos vencidos');
            return;
        }

        $this->error("⚠️  ALERTA: {$expiredDocuments->count()} documentos ya VENCIDOS:");
        $this->newLine();

        foreach ($expiredDocuments as $document) {
            $providerName = $document->provider->business_name ?? 'Sin nombre';
            $documentType = $document->documentType->name ?? 'Sin tipo';
            $expiryDate = Carbon::parse($document->expiry_date);
            $daysExpired = $expiryDate->diffInDays(Carbon::today());

            $this->line("  • {$providerName}");
            $this->line("    {$documentType} - Vencido hace {$daysExpired} días");
            $this->line("    Fecha de vencimiento: " . $expiryDate->format('d/m/Y'));
        }

        $this->newLine();
    }

    /**
     * Obtener email del proveedor
     */
    protected function getProviderEmail($document)
    {
        // Intentar obtener email del proveedor
        $provider = $document->provider;

        // Opción 1: Email directo del proveedor
        if (!empty($provider->email)) {
            return $provider->email;
        }

        // Opción 2: Email de contacto
        if (!empty($provider->contact_email)) {
            return $provider->contact_email;
        }

        // Opción 3: Email del primer contacto (si existe la relación)
        if (method_exists($provider, 'contacts')) {
            $contacts = $provider->contacts;
            if ($contacts && $contacts->isNotEmpty()) {
                $contact = $contacts->first();
                if (!empty($contact->email)) {
                    return $contact->email;
                }
            }
        }

        return null;
    }
}