<?php

namespace App\Console\Commands;

use App\Models\NetsuiteCreditMemo;
use App\Models\NetsuiteVendorInvoice;
use App\Models\NetsuiteVendorPayment;
use App\Models\Provider;
use App\Services\NetSuiteClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncNetSuiteVendorData extends Command
{
    protected $signature = 'netsuite:sync-vendor-data {--since=2026-01-01}';
    protected $description = 'Sincroniza facturas, pagos y notas de crédito de proveedores desde NetSuite';

    public function handle(NetSuiteClient $client): int
    {
        $since = $this->option('since');
        $startedAt = microtime(true);

        // Mapa netsuite_internal_id => provider_id, para resolver el dueño
        // de cada documento sin tener que consultar uno por uno.
        $providerMap = Provider::whereNotNull('netsuite_internal_id')
            ->pluck('id', 'netsuite_internal_id');

        $this->info("Proveedores vinculados a NetSuite: {$providerMap->count()}\n");

        $entityIds = $providerMap->keys()->all(); // los netsuite_internal_id ya vinculados

        $totals = [
            'invoices' => $this->syncInvoices($client, $providerMap, $since, $entityIds),
            'payments' => $this->syncPayments($client, $providerMap, $since, $entityIds),
            'credit_memos' => $this->syncCreditMemos($client, $providerMap, $since, $entityIds),
        ];

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        foreach ($totals as $module => $count) {
            DB::table('netsuite_sync_logs')->insert([
                'module' => $module,
                'records_synced' => $count,
                'status' => 'exitoso',
                'message' => "{$count} registros sincronizados desde {$since}",
                'duration_ms' => $durationMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("\n✅ Sincronización completa en " . round($durationMs / 1000, 1) . 's');
        $this->table(['Módulo', 'Registros'], collect($totals)->map(fn ($v, $k) => [$k, $v])->toArray());

        return self::SUCCESS;
    }

   protected function syncInvoices(NetSuiteClient $client, $providerMap, string $since, array $entityIds): int
    {
        $this->info('[1/3] Sincronizando facturas...');
        $rows = $client->getVendorBillsSince($since, $entityIds);
        $count = 0;

        foreach ($rows as $row) {
            $providerId = $providerMap->get($row['entity'] ?? null);
            if (!$providerId) continue; // proveedor aún no vinculado, se ignora por ahora

            $lastModified = $this->parseNsDate($row['lastmodifieddate'] ?? null);
            $existing = NetsuiteVendorInvoice::where('netsuite_internal_id', $row['id'])->first();

            $needsStatusRefresh = !$existing
                || !$existing->netsuite_last_modified
                || $existing->netsuite_last_modified->ne($lastModified);

            $status = $needsStatusRefresh
                ? $client->getVendorBillStatus($row['id'])
                : $existing->status;

            NetsuiteVendorInvoice::updateOrCreate(
                ['netsuite_internal_id' => $row['id']],
                [
                    'provider_id' => $providerId,
                    'tran_id' => $row['tranid'] ?? null,
                    'tran_date' => $this->parseNsDate($row['trandate'] ?? null),
                    'due_date' => $this->parseNsDate($row['duedate'] ?? null),
                    'amount' => abs((float) ($row['foreigntotal'] ?? 0)),
                    'status' => $status,
                    'pdf_file_id' => $row['custbodypdf_compras'] ?? null,
                    'netsuite_last_modified' => $lastModified,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }

        $this->info("  ✓ {$count} facturas\n");
        return $count;
    }

    protected function syncPayments(NetSuiteClient $client, $providerMap, string $since, array $entityIds): int
    {
        $this->info('[2/3] Sincronizando pagos...');
        $rows = $client->getVendorPaymentsSince($since, $entityIds);
        $count = 0;

        foreach ($rows as $row) {
            $providerId = $providerMap->get($row['entity'] ?? null);
            if (!$providerId) continue;

            NetsuiteVendorPayment::updateOrCreate(
                ['netsuite_internal_id' => $row['id']],
                [
                    'provider_id' => $providerId,
                    'tran_id' => $row['tranid'] ?? null,
                    'tran_date' => $this->parseNsDate($row['trandate'] ?? null),
                    'amount' => abs((float) ($row['foreigntotal'] ?? 0)),
                    'receipt_file_id' => $row['custbody17'] ?? null,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }

        $this->info("  ✓ {$count} pagos\n");
        return $count;
    }

    protected function syncCreditMemos(NetSuiteClient $client, $providerMap, string $since, array $entityIds): int
    {
        $this->info('[3/3] Sincronizando notas de crédito...');
        $rows = $client->getVendorCreditMemosSince($since, $entityIds);
        $count = 0;

        foreach ($rows as $row) {
            $providerId = $providerMap->get($row['entity'] ?? null);
            if (!$providerId) continue;

            $lastModified = $this->parseNsDate($row['lastmodifieddate'] ?? null);
            $existing = NetsuiteCreditMemo::where('netsuite_internal_id', $row['id'])->first();

            $needsRefresh = !$existing
                || !$existing->netsuite_last_modified
                || $existing->netsuite_last_modified->ne($lastModified);

            $amount = abs((float) ($row['foreigntotal'] ?? 0));
            $remaining = $needsRefresh
                ? ($client->getCreditMemoUnapplied($row['id']) ?? $amount)
                : $existing->amount_remaining;

            NetsuiteCreditMemo::updateOrCreate(
                ['netsuite_internal_id' => $row['id']],
                [
                    'provider_id' => $providerId,
                    'tran_id' => $row['tranid'] ?? null,
                    'tran_date' => $this->parseNsDate($row['trandate'] ?? null),
                    'amount' => $amount,
                    'amount_remaining' => $remaining,
                    'netsuite_last_modified' => $lastModified,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }

        $this->info("  ✓ {$count} notas de crédito\n");
        return $count;
    }

    /**
     * NetSuite regresa fechas como "dd/mm/yyyy" vía SuiteQL.
     */
    protected function parseNsDate(?string $value): ?\Carbon\Carbon
    {
        if (!$value) return null;
        return \Carbon\Carbon::createFromFormat('d/m/Y', $value);
    }
}