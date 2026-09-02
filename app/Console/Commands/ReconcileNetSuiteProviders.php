<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Services\NetSuiteClient;
use Illuminate\Console\Command;

class ReconcileNetSuiteProviders extends Command
{
    protected $signature = 'netsuite:reconcile-providers {--dry-run : Solo muestra qué haría, sin guardar cambios}';
    protected $description = 'Cruza los proveedores de SGP con los vendors de NetSuite por RFC y guarda el netsuite_internal_id';

    public function handle(NetSuiteClient $client): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Obteniendo vendors de NetSuite (con RFC capturado)...');
        $vendorsByRfc = $this->fetchAllVendors($client);
        $this->info('Vendors con RFC encontrados en NetSuite: ' . count($vendorsByRfc) . "\n");

        $providers = Provider::whereNull('netsuite_internal_id')
            ->whereNotNull('rfc')
            ->get();

        $this->info("Proveedores en SGP sin vincular: {$providers->count()}\n");

        $matched = [];
        $unmatched = [];

        foreach ($providers as $provider) {
            $normalizedRfc = strtoupper(trim($provider->rfc));

            if (isset($vendorsByRfc[$normalizedRfc])) {
                $vendor = $vendorsByRfc[$normalizedRfc];

                if (!$dryRun) {
                    $provider->update(['netsuite_internal_id' => $vendor['id']]);
                }

                $matched[] = [
                    $provider->id,
                    $provider->business_name,
                    $normalizedRfc,
                    $vendor['id'],
                    $vendor['companyname'],
                ];
            } else {
                $unmatched[] = [$provider->id, $provider->business_name, $normalizedRfc];
            }
        }

        if (count($matched) > 0) {
            $this->info('✅ Vinculados (' . count($matched) . '):');
            $this->table(
                ['Provider ID', 'Razón Social (SGP)', 'RFC', 'NetSuite ID', 'Razón Social (NetSuite)'],
                $matched
            );
        }

        if (count($unmatched) > 0) {
            $this->warn("\n⚠️  Sin coincidencia (" . count($unmatched) . ') — revisar manualmente:');
            $this->table(['Provider ID', 'Razón Social (SGP)', 'RFC'], $unmatched);
        }

        if ($dryRun) {
            $this->comment("\n(dry-run: no se guardó ningún cambio, solo simulación)");
        }

        return self::SUCCESS;
    }

    /**
     * Trae todos los vendors de NetSuite que tienen RFC capturado,
     * paginando de 1000 en 1000, y regresa un mapa [RFC => ['id' => ..., 'companyname' => ...]]
     */
    protected function fetchAllVendors(NetSuiteClient $client): array
    {
        $pageSize = 1000;
        $offset = 0;
        $vendorsByRfc = [];

        do {
            $query = "SELECT id, companyname, custentity_ent_entloc_rfc AS rfc
                      FROM vendor
                      WHERE custentity_ent_entloc_rfc IS NOT NULL
                      ORDER BY id
                      OFFSET {$offset} ROWS FETCH NEXT {$pageSize} ROWS ONLY";

            $page = $client->suiteql($query);

            foreach ($page as $row) {
                $rfc = strtoupper(trim($row['rfc'] ?? ''));

                if ($rfc !== '') {
                    $vendorsByRfc[$rfc] = [
                        'id'          => $row['id'],
                        'companyname' => $row['companyname'] ?? null,
                    ];
                }
            }

            $offset += $pageSize;
        } while (count($page) === $pageSize);

        return $vendorsByRfc;
    }
}