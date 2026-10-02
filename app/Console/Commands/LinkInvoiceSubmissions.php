<?php

namespace App\Console\Commands;

use App\Models\ProviderInvoiceSubmission;
use App\Models\User;
use App\Notifications\InvoiceSubmissionNotification;
use App\Services\NetSuiteClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LinkInvoiceSubmissions extends Command
{
    protected $signature = 'netsuite:link-invoice-submissions {--since=2026-01-01}';
    protected $description = 'Enlaza automáticamente las facturas subidas por proveedores con su captura en NetSuite, cruzando por UUID fiscal';

    public function handle(NetSuiteClient $client): int
    {
        ini_set('memory_limit', '512M');

        // Revisa todas las que siguen "vivas" (no rechazadas ni canceladas) —
        // esto cubre tanto el enlace inicial como la re-verificación de las
        // ya capturadas, por si el registro de NetSuite cambió de ID interno.
        $toProcess = ProviderInvoiceSubmission::whereIn('status', [
            ProviderInvoiceSubmission::STATUS_SUBMITTED,
            ProviderInvoiceSubmission::STATUS_CAPTURED,
        ])->get();

        if ($toProcess->isEmpty()) {
            $this->info('No hay facturas que revisar.');
            return self::SUCCESS;
        }

        $this->info("Facturas a revisar: {$toProcess->count()}");
        $this->info('Consultando facturas con UUID en NetSuite...');

        $nsInvoices = $client->getVendorBillsWithUuidSince($this->option('since'));
        $byUuid = collect($nsInvoices)->keyBy(fn ($row) => strtoupper(trim($row['uuid'])));

        $this->info('Facturas con UUID en NetSuite: ' . $byUuid->count());

        $linked = 0;
        $backfilled = 0;
        $relinked = 0;

        foreach ($toProcess as $submission) {
            $nsRow = $byUuid->get($submission->uuid);

            if (!$nsRow) {
                continue;
            }

            $netsuiteInvoice = \App\Models\NetsuiteVendorInvoice::where('netsuite_internal_id', $nsRow['id'])->first();

            if (!$netsuiteInvoice) {
                continue; // el sync local aún no trae esta factura
            }

            $wasSubmitted = $submission->status === ProviderInvoiceSubmission::STATUS_SUBMITTED;
            $originalLink = $submission->netsuite_vendor_invoice_id;
            $linkChanged = $originalLink !== $netsuiteInvoice->id;

            if (!$wasSubmitted && !$linkChanged) {
                continue; // ya estaba correctamente enlazada, nada que hacer
            }

            $submission->update([
                'status' => ProviderInvoiceSubmission::STATUS_CAPTURED,
                'netsuite_vendor_invoice_id' => $netsuiteInvoice->id,
                'captured_at' => $submission->captured_at ?? now(),
            ]);

            Log::info('Factura enlazada/actualizada con NetSuite por UUID', [
                'submission_id' => $submission->id,
                'uuid' => $submission->uuid,
                'netsuite_vendor_invoice_id' => $netsuiteInvoice->id,
                'was_submitted' => $wasSubmitted,
                'link_changed' => $linkChanged,
            ]);

            if ($wasSubmitted) {
                $this->notifyProvider($submission);
                $linked++;
            } elseif ($originalLink === null) {
                $backfilled++;
            } else {
                $relinked++;
            }
        }

        $this->info("Enlazadas (nuevas, con notificación): {$linked}");
        $this->info("Enlazadas (backfill, sin notificación duplicada): {$backfilled}");
        $this->info("Re-enlazadas (el registro de NetSuite cambió): {$relinked}");

        return self::SUCCESS;
    }

    protected function notifyProvider(ProviderInvoiceSubmission $submission): void
    {
        try {
            $user = User::where('email', $submission->provider->email)->first();
            $user?->notify(new InvoiceSubmissionNotification($submission, 'captured'));
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar al proveedor tras enlace automático', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}