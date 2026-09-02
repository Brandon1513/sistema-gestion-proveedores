<?php

namespace App\Console\Commands;

use App\Services\NetSuiteClient;
use Illuminate\Console\Command;

class TestNetSuiteConnection extends Command
{
    protected $signature = 'netsuite:test-connection {--query= : Query SuiteQL} {--record= : formato recordType:id, ej. vendor:1253} {--file= : ID de archivo del File Cabinet}';
    protected $description = 'Prueba la conexión TBA a NetSuite';
    

    public function handle(NetSuiteClient $client): int
    {
        try {
            if ($fileId = $this->option('file')) {
                $this->info("Probando RESTlet de descarga, file={$fileId}...\n");
                $result = $client->downloadFile($fileId);
                $this->info("✅ Archivo: {$result['name']} ({$result['fileType']})");
                $this->line('Primeros 100 caracteres del contenido base64: ' . substr($result['content'], 0, 100));
                return self::SUCCESS;
            }

            if ($record = $this->option('record')) {
                [$recordType, $id] = explode(':', $record);
                $this->info("Probando GET record/v1/{$recordType}/{$id}...\n");
                $result = $client->getRecord($recordType, $id);
            } else {
                $query = $this->option('query') ?: "SELECT id, tranid FROM transaction WHERE type = 'VendBill' FETCH FIRST 5 ROWS ONLY";
                $this->info("Probando SuiteQL:\n{$query}\n");
                $result = $client->suiteql($query);
            }

            $this->info('✅ Conexión exitosa. Resultado:');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Falló: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}