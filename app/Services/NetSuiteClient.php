<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NetSuiteClient
{
    protected string $accountId;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $tokenId;
    protected string $tokenSecret;
    protected string $baseUrl;
    protected string $fileRestletScriptId;
    protected string $fileRestletDeployId;

    public function __construct()
    {
        $this->accountId      = config('services.netsuite.account_id');
        $this->consumerKey    = config('services.netsuite.consumer_key');
        $this->consumerSecret = config('services.netsuite.consumer_secret');
        $this->tokenId        = config('services.netsuite.token_id');
        $this->tokenSecret    = config('services.netsuite.token_secret');
        $this->fileRestletScriptId = config('services.netsuite.file_restlet_script_id');
        $this->fileRestletDeployId = config('services.netsuite.file_restlet_deploy_id');
        $this->baseUrl         = "https://{$this->accountId}.suitetalk.api.netsuite.com/services/rest";
    }

    /**
     * Ejecuta una consulta SuiteQL (SELECT) y regresa el arreglo de resultados.
     * Equivalente a suiteql() en tu cliente de Node.
     */
    public function suiteql(string $query): array
    {
        $url = "{$this->baseUrl}/query/v1/suiteql";
        $method = 'POST';

        $response = Http::withHeaders([
            'Authorization' => $this->buildAuthHeader($url, $method),
            'Content-Type'  => 'application/json',
            'Prefer'        => 'transient',
        ])->post($url, ['q' => $query]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "NetSuite SuiteQL falló ({$response->status()}): {$response->body()}"
            );
        }

        return $response->json('items') ?? [];
    }

    /**
     * Descarga un archivo del File Cabinet de NetSuite vía el RESTlet propio
     * (el endpoint REST estándar no soporta el tipo 'file' directamente).
     * Regresa ['name' => ..., 'fileType' => ..., 'content' => base64...]
     */
    public function downloadFile(string $fileId): array
    {
        $url = "https://{$this->accountId}.restlets.api.netsuite.com/app/site/hosting/restlet.nl"
            . "?script={$this->fileRestletScriptId}&deploy={$this->fileRestletDeployId}&fileId={$fileId}";
        $method = 'GET';

        $response = Http::withHeaders([
            'Authorization' => $this->buildAuthHeader($url, $method),
        ])->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("NetSuite downloadFile falló ({$response->status()}): {$response->body()}");
        }

        // El RESTlet regresa el JSON como string (json_decode del body crudo,
        // no $response->json() directo, porque a veces viene doblemente
        // envuelto en comillas por el propio runtime de SuiteScript).
        $raw = $response->body();
        $data = json_decode($raw, true);

        // Si json_decode del primer nivel regresó otro string (doble-encoding),
        // decodificamos una vez más.
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            throw new \RuntimeException("NetSuite downloadFile: respuesta no parseable como JSON: {$raw}");
        }

        if (isset($data['error'])) {
            throw new \RuntimeException("NetSuite downloadFile error: {$data['error']}");
        }

        return $data;
    }

    // Agregar este método dentro de NetSuiteClient.php, junto a suiteql()

        public function getRecord(string $recordType, string $id): array
        {
            $url = "{$this->baseUrl}/record/v1/{$recordType}/{$id}";
            $method = 'GET';

            $response = Http::withHeaders([
                'Authorization' => $this->buildAuthHeader($url, $method),
                'Content-Type'  => 'application/json',
            ])->get($url);

            if ($response->failed()) {
                throw new \RuntimeException(
                    "NetSuite getRecord falló ({$response->status()}): {$response->body()}"
                );
            }

            return $response->json();
        }
    /**
     * Construye el header de autorización OAuth 1.0a (HMAC-SHA256) para TBA.
     * Mismo esquema que buildAuthHeader() en tu netsuite.js.
     */
    protected function buildAuthHeader(string $url, string $method): string
    {
        $oauthParams = [
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_token'            => $this->tokenId,
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp'        => (string) time(),
            'oauth_nonce'            => Str::random(32),
            'oauth_version'          => '1.0',
        ];

        // Para requests con query string, sus parámetros también entran a la firma.
        $parsedUrl = parse_url($url);
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }
        $baseUrlWithoutQuery = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];

        $allParams = array_merge($oauthParams, $queryParams);
        ksort($allParams);

        $paramString = collect($allParams)
            ->map(fn ($value, $key) => rawurlencode($key) . '=' . rawurlencode($value))
            ->implode('&');

        $baseString = strtoupper($method) . '&' . rawurlencode($baseUrlWithoutQuery) . '&' . rawurlencode($paramString);

        $signingKey = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->tokenSecret);
        $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));

        $oauthParams['oauth_signature'] = $signature;

        $headerParts = collect($oauthParams)
            ->map(fn ($value, $key) => rawurlencode($key) . '="' . rawurlencode($value) . '"')
            ->implode(', ');

        return "OAuth realm=\"{$this->accountId}\", {$headerParts}";
    }

    // Agregar dentro de app/Services/NetSuiteClient.php

/**
 * Trae en bloque todas las facturas de proveedor (VendBill) desde una fecha,
 * paginando de 1000 en 1000. Una sola pasada trae TODOS los proveedores,
 * en vez de consultar uno por uno.
 */
public function getVendorBillsSince(string $sinceDate, array $entityIds): array
{
    return $this->paginatedTransactionQuery('VendBill', [
        'id', 'tranid', 'trandate', 'duedate', 'foreigntotal', 'entity', 'lastmodifieddate', 'custbodypdf_compras',
    ], $sinceDate, $entityIds);
}

public function getVendorPaymentsSince(string $sinceDate, array $entityIds): array
{
    return $this->paginatedTransactionQuery('VendPymt', [
        'id', 'tranid', 'trandate', 'foreigntotal', 'entity', 'custbody17',
    ], $sinceDate, $entityIds);
}

public function getVendorCreditMemosSince(string $sinceDate, array $entityIds): array
{
    return $this->paginatedTransactionQuery('VendCred', [
        'id', 'tranid', 'trandate', 'foreigntotal', 'entity', 'lastmodifieddate',
    ], $sinceDate, $entityIds);
}

/**
 * Trae el status legible (ej. "Pagado por completo") de una factura específica.
 * Solo se llama para documentos nuevos o modificados, no para todos cada vez.
 */
public function getVendorBillStatus(string $id): ?string
{
    $record = $this->getRecord('vendorbill', $id);
    return $record['status']['refName'] ?? null;
}

/**
 * Trae el saldo no aplicado (unapplied) de una nota de crédito específica.
 * Igual que arriba, solo se llama para nuevas/modificadas.
 */
public function getCreditMemoUnapplied(string $id): ?float
{
    $record = $this->getRecord('vendorcredit', $id);
    return isset($record['unapplied']) ? (float) $record['unapplied'] : null;
}

protected function paginatedTransactionQuery(string $type, array $columns, string $sinceDate, array $entityIds = []): array
{
    if (empty($entityIds)) {
        return []; // sin proveedores vinculados, no hay nada que traer
    }

    $pageSize = 1000;
    $offset = 0;
    $all = [];
    $columnList = implode(', ', $columns);
    $entityList = implode(',', array_map('intval', $entityIds));

    do {
        $query = "SELECT {$columnList}
                  FROM transaction
                  WHERE type = '{$type}'
                    AND trandate >= TO_DATE('{$sinceDate}', 'YYYY-MM-DD')
                    AND entity IN ({$entityList})
                  ORDER BY id
                  OFFSET {$offset} ROWS FETCH NEXT {$pageSize} ROWS ONLY";

        $page = $this->suiteql($query);
        $all = array_merge($all, $page);
        $offset += $pageSize;
    } while (count($page) === $pageSize);

    return $all;
}

    /**
     * Trae los documentos aplicados a una factura (pagos y notas de
     * crédito), vía la tabla de links de NetSuite.
     */
    public function getAppliedTransactions(string $invoiceInternalId): array
    {
        $query = "SELECT nextdoc, linktype
                FROM PreviousTransactionLineLink
                WHERE previousdoc = {$invoiceInternalId}";

        return $this->suiteql($query);
    }

    /**
     * Dado un pago o nota de crédito, encuentra la(s) factura(s) a las
     * que fue aplicado (dirección inversa de getAppliedTransactions).
     */
    public function getSourceTransactions(string $transactionInternalId): array
    {
        $query = "SELECT previousdoc FROM PreviousTransactionLineLink WHERE nextdoc = {$transactionInternalId}";
        return $this->suiteql($query);
    }
}