<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetsuiteCreditMemo;
use App\Models\NetsuiteVendorInvoice;
use App\Models\NetsuiteVendorPayment;
use App\Models\Provider;
use App\Models\ProviderCreditNoteRequest;
use App\Services\NetSuiteClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Notifications\CreditNoteResponseReceivedNotification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;




class ProviderAccountStatementController extends Controller
{
    /**
     * Resuelve el Provider ligado al usuario autenticado (mismo patrón
     * que el resto del portal proveedor: match por email).
     */
    protected function currentProvider(Request $request): Provider
    {
        return Provider::where('email', $request->user()->email)->firstOrFail();
    }

    /**
     * Estado de cuenta completo: facturas, pagos, notas de crédito de
     * NetSuite, y las solicitudes de nota de crédito que el proveedor
     * mismo ha subido. Todo filtrado estrictamente por su propio provider_id.
     */
    public function index(Request $request): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $invoices = NetsuiteVendorInvoice::where('provider_id', $provider->id)
            ->orderByDesc('tran_date')
            ->get();

        $payments = NetsuiteVendorPayment::where('provider_id', $provider->id)
            ->orderByDesc('tran_date')
            ->get();

        $creditMemos = NetsuiteCreditMemo::where('provider_id', $provider->id)
            ->orderByDesc('tran_date')
            ->get();

        $creditNoteRequests = ProviderCreditNoteRequest::where('provider_id', $provider->id)
            ->with('relatedInvoice:id,tran_id')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'invoices' => $invoices,
            'payments' => $payments,
            'credit_memos' => $creditMemos,
            'credit_note_requests' => $creditNoteRequests,
            'summary' => [
                'total_pending' => $invoices->where('status', '!=', 'Pagado por completo')->sum('amount'),
                'invoices_count' => $invoices->count(),
                'last_synced_at' => $invoices->max('last_synced_at'),
            ],
        ]);
    }

    public function downloadNetSuiteFile(Request $request, NetSuiteClient $client, string $type, int $id)
    {
        $provider = $this->currentProvider($request);

        if ($type === 'invoice') {
            $record = NetsuiteVendorInvoice::where('provider_id', $provider->id)->findOrFail($id);
            $fileId = $record->pdf_file_id;
        } elseif ($type === 'payment') {
            $record = NetsuiteVendorPayment::where('provider_id', $provider->id)->findOrFail($id);
            $fileId = $record->receipt_file_id;
        } else {
            abort(404);
        }

        if (!$fileId) {
            abort(404, 'Este documento no tiene archivo adjunto en NetSuite');
        }

        $file = $client->downloadFile($fileId);
        $contentType = $this->mimeTypeFromNetSuiteType($file['fileType'] ?? '');
        $filename = $file['name'] ?? "documento.{$this->extensionFromMime($contentType)}";

        Log::info('Descarga de comprobante NetSuite', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'provider_id' => $provider->id,
            'type' => $type,
            'record_id' => $id,
            'netsuite_file_id' => $fileId,
            'filename' => $filename,
            'ip' => $request->ip(),
        ]);

        return response(base64_decode($file['content']))
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * NetSuite regresa el tipo de archivo con nombres propios (PDF, JPGIMAGE,
     * PNGIMAGE, etc.), no MIME types estándar — los traducimos.
     */
    protected function mimeTypeFromNetSuiteType(string $nsType): string
    {
        return match (strtoupper($nsType)) {
            'PDF' => 'application/pdf',
            'JPGIMAGE' => 'image/jpeg',
            'PNGIMAGE' => 'image/png',
            'GIFIMAGE' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    protected function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }

    /**
     * El proveedor sube el CFDI de la nota de crédito que ya generó,
     * como comprobante de que atendió la solicitud de Finanzas/Compras.
     */
    public function respondToCreditNoteRequest(Request $request, int $id): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $creditNoteRequest = ProviderCreditNoteRequest::where('provider_id', $provider->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,xml', 'max:10240'],
        ]);

        $path = $request->file('file')->store(
            "credit-note-requests/{$provider->id}/responses",
            'local'
        );

        $creditNoteRequest->update([
            'provider_response_file_path' => $path,
            'provider_responded_at' => now(),
            'status' => $creditNoteRequest->status === 'pending' ? 'in_review' : $creditNoteRequest->status,
        ]);

        $this->notifyFinanceTeam($creditNoteRequest->load('provider', 'relatedInvoice'));

        return response()->json([
            'message' => 'CFDI subido correctamente',
            'credit_note_request' => $creditNoteRequest->fresh(),
        ]);
    }

    /**
     * Descarga del CFDI que el propio proveedor subió (por si quiere
     * verificar qué archivo quedó guardado).
     */
    public function downloadMyResponseFile(Request $request, int $id)
    {
        $provider = $this->currentProvider($request);

        $creditNoteRequest = ProviderCreditNoteRequest::where('provider_id', $provider->id)
            ->findOrFail($id);

        if (!$creditNoteRequest->provider_response_file_path
            || !Storage::disk('local')->exists($creditNoteRequest->provider_response_file_path)) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::disk('local')->download($creditNoteRequest->provider_response_file_path);
    }

    protected function notifyFinanceTeam(ProviderCreditNoteRequest $creditNoteRequest): void
    {
        $staff = User::role(['finanzas', 'compras'])->get();

        foreach ($staff as $user) {
            $user->notify(new CreditNoteResponseReceivedNotification($creditNoteRequest));
        }
    }

    /**
     * Timeline de una factura: sus pagos y notas de crédito aplicados,
     * cruzando los datos ya sincronizados en las tablas locales.
     */
    public function invoiceTimeline(Request $request, NetSuiteClient $client, int $id): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $invoice = NetsuiteVendorInvoice::where('provider_id', $provider->id)
            ->findOrFail($id);

        $links = $client->getAppliedTransactions($invoice->netsuite_internal_id);
        $appliedIds = collect($links)->pluck('nextdoc');

        $payments = NetsuiteVendorPayment::whereIn('netsuite_internal_id', $appliedIds)
            ->where('provider_id', $provider->id)
            ->get();

        $creditMemos = NetsuiteCreditMemo::whereIn('netsuite_internal_id', $appliedIds)
            ->where('provider_id', $provider->id)
            ->get();

        return response()->json([
            'invoice' => $invoice,
            'applied_payments' => $payments,
            'applied_credit_memos' => $creditMemos,
        ]);
    }

    public function paymentRelatedInvoices(Request $request, NetSuiteClient $client, int $id): JsonResponse
    {
        $provider = $this->currentProvider($request);
        $payment = NetsuiteVendorPayment::where('provider_id', $provider->id)->findOrFail($id);
        return $this->resolveRelatedInvoices($client, $payment->netsuite_internal_id, $provider->id);
    }

    public function creditMemoRelatedInvoices(Request $request, NetSuiteClient $client, int $id): JsonResponse
    {
        $provider = $this->currentProvider($request);
        $creditMemo = NetsuiteCreditMemo::where('provider_id', $provider->id)->findOrFail($id);
        return $this->resolveRelatedInvoices($client, $creditMemo->netsuite_internal_id, $provider->id);
    }

    protected function resolveRelatedInvoices(NetSuiteClient $client, string $transactionInternalId, int $providerId): JsonResponse
    {
        $links = $client->getSourceTransactions($transactionInternalId);
        $invoiceIds = collect($links)->pluck('previousdoc');

        $invoices = NetsuiteVendorInvoice::whereIn('netsuite_internal_id', $invoiceIds)
            ->where('provider_id', $providerId)
            ->get(['id', 'tran_id', 'amount', 'status']);

        return response()->json(['invoices' => $invoices]);
    }

    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $provider = $this->currentProvider($request);

        $invoices = NetsuiteVendorInvoice::where('provider_id', $provider->id)
            ->orderByDesc('tran_date')->get();
        $payments = NetsuiteVendorPayment::where('provider_id', $provider->id)
            ->orderByDesc('tran_date')->get();
        $creditMemos = NetsuiteCreditMemo::where('provider_id', $provider->id)
            ->orderByDesc('tran_date')->get();

        $spreadsheet = new Spreadsheet();

        $this->writeSheet($spreadsheet->getActiveSheet(), 'Facturas', [
            ['Folio', 'Fecha', 'Vencimiento', 'Monto', 'Status'],
        ], $invoices->map(fn ($i) => [
            $i->tran_id, $i->tran_date?->format('d/m/Y'), $i->due_date?->format('d/m/Y'),
            (float) $i->amount, $i->status,
        ])->toArray());

        $this->writeSheet($spreadsheet->createSheet(), 'Pagos', [
            ['Folio', 'Fecha', 'Monto'],
        ], $payments->map(fn ($p) => [
            $p->tran_id, $p->tran_date?->format('d/m/Y'), (float) $p->amount,
        ])->toArray());

        $this->writeSheet($spreadsheet->createSheet(), 'Notas de Crédito', [
            ['Folio', 'Fecha', 'Monto', 'Saldo disponible'],
        ], $creditMemos->map(fn ($c) => [
            $c->tran_id, $c->tran_date?->format('d/m/Y'), (float) $c->amount, (float) $c->amount_remaining,
        ])->toArray());

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'estado-cuenta-' . str($provider->business_name)->slug() . '-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Escribe encabezado con estilo + filas de datos en una hoja. Compartido
     * entre el export del proveedor y el de finanzas.
     */
   protected function writeSheet($sheet, string $title, array $headerRows, array $dataRows): void
{
    $sheet->setTitle($title);

    $headers = $headerRows[0];
    foreach ($headers as $col => $label) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
        $cell = $sheet->getCell("{$colLetter}1");
        $cell->setValue($label);
        $cell->getStyle()->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6A2C75');
    }

    $rowNum = 2;
    foreach ($dataRows as $row) {
        foreach ($row as $col => $value) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$colLetter}{$rowNum}", $value);
        }
        $rowNum++;
    }

    foreach (range(1, count($headers)) as $col) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setName('Arial');
    $sheet->getStyle('A2:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->getFont()->setName('Arial');
}

}