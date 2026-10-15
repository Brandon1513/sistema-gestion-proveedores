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
use Illuminate\Support\Str;

class SyncNetSuiteVendorData extends Command
{
    protected $signature = 'netsuite:sync-vendor-data {--since=2026-01-01} {--provider-id=}';
    protected $description = 'Sincroniza facturas, pagos y notas de crédito de proveedores desde NetSuite';

    /** Tras tantos fallos seguidos asumimos que NetSuite o la red no responden. */
    private const MAX_CONSECUTIVE_FAILURES = 5;

    /** Detalles que no se pudieron refrescar en esta corrida (se reintentan en la siguiente). */
    protected array $refreshFailures = ['invoices' => 0, 'credit_memos' => 0];

    public function handle(NetSuiteClient $client): int
    {
        $since = $this->option('since');
        $startedAt = microtime(true);

        // Mapa netsuite_internal_id => provider_id
        $providerMap = Provider::whereNotNull('netsuite_internal_id')
            ->pluck('id', 'netsuite_internal_id');

        $onlyProviderId = $this->option('provider-id');

        if ($onlyProviderId) {
            $providerMap = $providerMap->filter(fn ($id) => (int) $id === (int) $onlyProviderId);

            if ($providerMap->isEmpty()) {
                $this->error("El proveedor {$onlyProviderId} no existe o no está vinculado a NetSuite.");
                return self::FAILURE;
            }
        }

        $this->info("Proveedores vinculados a NetSuite: {$providerMap->count()}\n");

        $entityIds = $providerMap->keys()->all();

        $jobs = [
            'invoices'     => fn () => $this->syncInvoices($client, $providerMap, $since, $entityIds),
            'payments'     => fn () => $this->syncPayments($client, $providerMap, $since, $entityIds),
            'credit_memos' => fn () => $this->syncCreditMemos($client, $providerMap, $since, $entityIds),
        ];

        $totals = [];
        $failedModules = [];

        foreach ($jobs as $module => $run) {
            try {
                $totals[$module] = $run();
            } catch (\Throwable $e) {
                $failedModules[$module] = $e->getMessage();
                Log::error("netsuite:sync-vendor-data: falló el módulo {$module}", ['error' => $e->getMessage()]);
                $this->error("  ✗ {$module}: " . Str::limit($e->getMessage(), 200) . "\n");
            }
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        foreach ($totals as $module => $count) {
            $pending = $this->refreshFailures[$module] ?? 0;

            DB::table('netsuite_sync_logs')->insert([
                'module' => $module,
                'records_synced' => $count,
                'status' => 'exitoso',
                'message' => "{$count} registros sincronizados desde {$since}"
                    . ($onlyProviderId ? " (proveedor {$onlyProviderId})" : '')
                    . ($pending ? " — {$pending} detalles pendientes de refrescar" : ''),
                'duration_ms' => $durationMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("\n" . (empty($failedModules) ? '✅ Sincronización completa' : '⚠️ Sincronización con errores') . ' en ' . round($durationMs / 1000, 1) . 's');
        $this->table(['Módulo', 'Registros'], collect($totals)->map(fn ($v, $k) => [$k, $v])->toArray());

        if (!empty($failedModules)) {
            $this->error('Módulos con error: ' . implode(', ', array_keys($failedModules)));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function syncInvoices(NetSuiteClient $client, $providerMap, string $since, array $entityIds): int
    {
        $this->info('[1/3] Sincronizando facturas...');
        $rows = $client->getVendorBillsSince($since, $entityIds);
        $count = 0;
        $failed = 0;
        $consecutive = 0;

        foreach ($rows as $row) {
            $providerId = $providerMap->get($row['entity'] ?? null);
            if (!$providerId) continue; // proveedor aún no vinculado, se ignora por ahora

            $lastModified = $this->parseNsDate($row['lastmodifieddate'] ?? null);
            $existing = NetsuiteVendorInvoice::where('netsuite_internal_id', $row['id'])->first();

            $needsStatusRefresh = !$existing
                || !$existing->netsuite_last_modified
                || $existing->netsuite_last_modified->ne($lastModified);

            $status = $existing?->status;
            $refreshFailed = false;

            if ($needsStatusRefresh) {
                try {
                    $status = $client->getVendorBillStatus($row['id']);
                    $consecutive = 0;
                } catch (\Throwable $e) {
                    $refreshFailed = true;
                    $failed++;
                    $consecutive++;
                    Log::warning('netsuite:sync-vendor-data: no se pudo refrescar el estatus de una factura', [
                        'netsuite_internal_id' => $row['id'],
                        'error' => $e->getMessage(),
                    ]);

                    if ($consecutive >= self::MAX_CONSECUTIVE_FAILURES) {
                        $this->refreshFailures['invoices'] += $failed;
                        throw new \RuntimeException(
                            'NetSuite no responde: ' . self::MAX_CONSECUTIVE_FAILURES . ' fallos seguidos al refrescar facturas'
                        );
                    }
                }
            }

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
                    // Si el refresco falló no la marcamos como "al día": se reintenta en la siguiente corrida.
                    'netsuite_last_modified' => $refreshFailed ? $existing?->netsuite_last_modified : $lastModified,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }

        $this->refreshFailures['invoices'] += $failed;
        $this->info("  ✓ {$count} facturas" . ($failed ? " ({$failed} con estatus pendiente de refrescar)" : '') . "\n");
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
        $failed = 0;
        $consecutive = 0;

        foreach ($rows as $row) {
            $providerId = $providerMap->get($row['entity'] ?? null);
            if (!$providerId) continue;

            $lastModified = $this->parseNsDate($row['lastmodifieddate'] ?? null);
            $existing = NetsuiteCreditMemo::where('netsuite_internal_id', $row['id'])->first();

            $needsRefresh = !$existing
                || !$existing->netsuite_last_modified
                || $existing->netsuite_last_modified->ne($lastModified);

            $amount = abs((float) ($row['foreigntotal'] ?? 0));
            $remaining = $existing?->amount_remaining;
            $refreshFailed = false;

            if ($needsRefresh) {
                try {
                    $remaining = $client->getCreditMemoUnapplied($row['id']) ?? $amount;
                    $consecutive = 0;
                } catch (\Throwable $e) {
                    $refreshFailed = true;
                    $failed++;
                    $consecutive++;
                    $remaining = $existing?->amount_remaining ?? $amount;
                    Log::warning('netsuite:sync-vendor-data: no se pudo refrescar el saldo de una nota de crédito', [
                        'netsuite_internal_id' => $row['id'],
                        'error' => $e->getMessage(),
                    ]);

                    if ($consecutive >= self::MAX_CONSECUTIVE_FAILURES) {
                        $this->refreshFailures['credit_memos'] += $failed;
                        throw new \RuntimeException(
                            'NetSuite no responde: ' . self::MAX_CONSECUTIVE_FAILURES . ' fallos seguidos al refrescar notas de crédito'
                        );
                    }
                }
            }

            NetsuiteCreditMemo::updateOrCreate(
                ['netsuite_internal_id' => $row['id']],
                [
                    'provider_id' => $providerId,
                    'tran_id' => $row['tranid'] ?? null,
                    'tran_date' => $this->parseNsDate($row['trandate'] ?? null),
                    'amount' => $amount,
                    'amount_remaining' => $remaining,
                    'netsuite_last_modified' => $refreshFailed ? $existing?->netsuite_last_modified : $lastModified,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }

        $this->refreshFailures['credit_memos'] += $failed;
        $this->info("  ✓ {$count} notas de crédito" . ($failed ? " ({$failed} con saldo pendiente de refrescar)" : '') . "\n");
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