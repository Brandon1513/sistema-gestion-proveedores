<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetsuiteCreditMemo;
use App\Models\NetsuiteVendorInvoice;
use App\Models\NetsuiteVendorPayment;
use App\Models\Provider;
use App\Models\ProviderCreditNoteRequest;
use App\Models\User;
use App\Notifications\CreditNoteRequestNotification;
use App\Services\NetSuiteClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;

class FinanceAccountStatementController extends Controller
{
    /**
     * Vista global de facturas, pagos y notas de crédito, filtrable
     * por proveedor. Para Compras/Finanzas — ven todos los proveedores,
     * a diferencia del endpoint del proveedor que solo ve el suyo.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['nullable', 'exists:providers,id'],
            'status' => ['nullable', 'string'],
        ]);

        $invoicesQuery = NetsuiteVendorInvoice::with('provider:id,business_name,rfc')
            ->orderByDesc('tran_date');

        $paymentsQuery = NetsuiteVendorPayment::with('provider:id,business_name,rfc')
            ->orderByDesc('tran_date');

        $creditMemosQuery = NetsuiteCreditMemo::with('provider:id,business_name,rfc')
            ->orderByDesc('tran_date');

        if (!empty($validated['provider_id'])) {
            $invoicesQuery->where('provider_id', $validated['provider_id']);
            $paymentsQuery->where('provider_id', $validated['provider_id']);
            $creditMemosQuery->where('provider_id', $validated['provider_id']);
        }

        if (!empty($validated['status'])) {
            $invoicesQuery->where('status', $validated['status']);
        }

        return response()->json([
            'invoices' => $invoicesQuery->paginate(50),
            'payments' => $paymentsQuery->paginate(50),
            'credit_memos' => $creditMemosQuery->paginate(50),
        ]);
    }

    /**
     * Autocompletado de proveedores por nombre, solo entre los que ya
     * están vinculados a NetSuite.
     */
    public function searchProviders(Request $request): JsonResponse
    {
        $query = trim($request->query('q', ''));

        $providers = Provider::whereNotNull('netsuite_internal_id')
            ->when($query !== '', function ($q) use ($query) {
                $q->where('business_name', 'ilike', "%{$query}%");
            })
            ->orderBy('business_name')
            ->limit(10)
            ->get(['id', 'business_name', 'rfc']);

        return response()->json($providers);
    }

    /**
     * Cola de solicitudes de nota de crédito, filtrable por status.
     */
    public function creditNoteRequests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'in_review', 'approved', 'rejected', 'sent_to_netsuite'])],
            'provider_id' => ['nullable', 'exists:providers,id'],
        ]);

        $query = ProviderCreditNoteRequest::with([
            'provider:id,business_name,rfc',
            'relatedInvoice:id,tran_id,amount',
            'reviewer:id,name',
        ])->orderByDesc('created_at');

        $query->where('status', $validated['status'] ?? 'pending');

        if (!empty($validated['provider_id'])) {
            $query->where('provider_id', $validated['provider_id']);
        }

        return response()->json($query->paginate(30));
    }

    /**
     * Finanzas/Compras crea la solicitud de nota de crédito a nombre de
     * un proveedor — el proveedor ya no la crea, solo la consulta y
     * recibe una notificación por correo.
     */
    public function storeCreditNoteRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'type' => ['required', Rule::in(['faltante', 'devolucion', 'rechazo'])],
            'description' => ['required', 'string', 'max:2000'],
            'amount_requested' => ['nullable', 'numeric', 'min:0'],
            'related_invoice_id' => ['nullable', 'exists:netsuite_vendor_invoices,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store(
                "credit-note-requests/{$validated['provider_id']}",
                'local'
            );
        }

        $creditNoteRequest = ProviderCreditNoteRequest::create([
            'provider_id' => $validated['provider_id'],
            'related_invoice_id' => $validated['related_invoice_id'] ?? null,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'amount_requested' => $validated['amount_requested'] ?? null,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        $provider = Provider::find($validated['provider_id']);
        $this->notifyProvider($provider, $creditNoteRequest->load('provider', 'relatedInvoice'), 'creada');

        return response()->json([
            'message' => 'Solicitud creada correctamente',
            'credit_note_request' => $creditNoteRequest->load('provider', 'relatedInvoice'),
        ], 201);
    }

    /**
     * Compras/Finanzas revisa una solicitud: aprueba, rechaza, o marca
     * que ya la llevó manualmente a NetSuite. Notifica al proveedor.
     */
    public function reviewCreditNoteRequest(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'approved', 'rejected', 'sent_to_netsuite'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $creditNoteRequest = ProviderCreditNoteRequest::findOrFail($id);

        $creditNoteRequest->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? $creditNoteRequest->review_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyProvider($creditNoteRequest->provider, $creditNoteRequest->load('provider', 'relatedInvoice'), 'actualizada');

        return response()->json([
            'message' => 'Solicitud actualizada',
            'credit_note_request' => $creditNoteRequest->fresh(['provider', 'relatedInvoice', 'reviewer']),
        ]);
    }

    /**
     * Notifica al usuario del proveedor por correo cuando se crea o
     * actualiza una solicitud de nota de crédito a su nombre.
     */
    protected function notifyProvider(Provider $provider, ProviderCreditNoteRequest $request, string $accion): void
    {
        $user = User::where('email', $provider->email)->first();

        if ($user) {
            $user->notify(new CreditNoteRequestNotification($request, $accion));
        }
    }

   public function downloadNetSuiteFile(Request $request, NetSuiteClient $client, string $type, int $id)
    {
        if ($type === 'invoice') {
            $record = NetsuiteVendorInvoice::findOrFail($id);
            $fileId = $record->pdf_file_id;
        } elseif ($type === 'payment') {
            $record = NetsuiteVendorPayment::findOrFail($id);
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

        Log::info('Descarga de comprobante NetSuite (Finanzas)', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'provider_id' => $record->provider_id,
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
     * Finanzas/Compras descarga el CFDI que el proveedor subió como
     * comprobante de su nota de crédito.
     */
    public function downloadProviderResponseFile(int $id)
    {
        $creditNoteRequest = ProviderCreditNoteRequest::findOrFail($id);

        if (!$creditNoteRequest->provider_response_file_path
            || !Storage::disk('local')->exists($creditNoteRequest->provider_response_file_path)) {
            abort(404, 'El proveedor aún no ha subido su CFDI');
        }

        return Storage::disk('local')->download($creditNoteRequest->provider_response_file_path);
    }

    public function invoiceTimeline(NetSuiteClient $client, int $id): JsonResponse
    {
        $invoice = NetsuiteVendorInvoice::findOrFail($id);

        $links = $client->getAppliedTransactions($invoice->netsuite_internal_id);
        $appliedIds = collect($links)->pluck('nextdoc');

        $payments = NetsuiteVendorPayment::whereIn('netsuite_internal_id', $appliedIds)
            ->where('provider_id', $invoice->provider_id)
            ->get();

        $creditMemos = NetsuiteCreditMemo::whereIn('netsuite_internal_id', $appliedIds)
            ->where('provider_id', $invoice->provider_id)
            ->get();

        return response()->json([
            'invoice' => $invoice->load('provider:id,business_name'),
            'applied_payments' => $payments,
            'applied_credit_memos' => $creditMemos,
        ]);
    }
    public function paymentRelatedInvoices(NetSuiteClient $client, int $id): JsonResponse
    {
        $payment = NetsuiteVendorPayment::findOrFail($id);
        return $this->resolveRelatedInvoices($client, $payment->netsuite_internal_id, $payment->provider_id);
    }

    public function creditMemoRelatedInvoices(NetSuiteClient $client, int $id): JsonResponse
    {
        $creditMemo = NetsuiteCreditMemo::findOrFail($id);
        return $this->resolveRelatedInvoices($client, $creditMemo->netsuite_internal_id, $creditMemo->provider_id);
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

    public function dashboardKpis(): JsonResponse
    {
        $today = now()->toDateString();

        $pendingScope = fn ($q) => $q->where(function ($q2) {
            $q2->whereNull('status')->orWhere('status', 'not ilike', '%pagado%');
        });

        $totalPending = NetsuiteVendorInvoice::tap($pendingScope)->sum('amount');

        $overdueQuery = NetsuiteVendorInvoice::tap($pendingScope)
            ->whereDate('due_date', '<', $today);

        $overdueCount = (clone $overdueQuery)->count();
        $overdueAmount = (clone $overdueQuery)->sum('amount');

        $topProviders = NetsuiteVendorInvoice::select('provider_id', DB::raw('SUM(amount) as pending_amount'))
            ->tap($pendingScope)
            ->groupBy('provider_id')
            ->orderByDesc('pending_amount')
            ->limit(5)
            ->with('provider:id,business_name')
            ->get()
            ->map(fn ($row) => [
                'provider_id' => $row->provider_id,
                'provider_name' => $row->provider->business_name ?? '—',
                'pending_amount' => (float) $row->pending_amount,
            ]);

        return response()->json([
            'total_pending' => (float) $totalPending,
            'overdue_count' => $overdueCount,
            'overdue_amount' => (float) $overdueAmount,
            'invoices_count' => NetsuiteVendorInvoice::count(),
            'providers_linked_count' => Provider::whereNotNull('netsuite_internal_id')->count(),
            'top_providers' => $topProviders,
        ]);
    }

    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $validated = $request->validate([
        'provider_id' => ['nullable', 'exists:providers,id'],
    ]);

    $invoicesQuery = NetsuiteVendorInvoice::with('provider:id,business_name')->orderByDesc('tran_date');
    $paymentsQuery = NetsuiteVendorPayment::with('provider:id,business_name')->orderByDesc('tran_date');
    $creditMemosQuery = NetsuiteCreditMemo::with('provider:id,business_name')->orderByDesc('tran_date');

    if (!empty($validated['provider_id'])) {
        $invoicesQuery->where('provider_id', $validated['provider_id']);
        $paymentsQuery->where('provider_id', $validated['provider_id']);
        $creditMemosQuery->where('provider_id', $validated['provider_id']);
    }

    $invoices = $invoicesQuery->get();
    $payments = $paymentsQuery->get();
    $creditMemos = $creditMemosQuery->get();

    $spreadsheet = new Spreadsheet();

    $this->writeSheet($spreadsheet->getActiveSheet(), 'Facturas', [
        ['Proveedor', 'Folio', 'Fecha', 'Vencimiento', 'Monto', 'Status'],
    ], $invoices->map(fn ($i) => [
        $i->provider->business_name ?? '—', $i->tran_id, $i->tran_date?->format('d/m/Y'),
        $i->due_date?->format('d/m/Y'), (float) $i->amount, $i->status,
    ])->toArray());

    $this->writeSheet($spreadsheet->createSheet(), 'Pagos', [
        ['Proveedor', 'Folio', 'Fecha', 'Monto'],
    ], $payments->map(fn ($p) => [
        $p->provider->business_name ?? '—', $p->tran_id, $p->tran_date?->format('d/m/Y'), (float) $p->amount,
    ])->toArray());

    $this->writeSheet($spreadsheet->createSheet(), 'Notas de Crédito', [
        ['Proveedor', 'Folio', 'Fecha', 'Monto', 'Saldo disponible'],
    ], $creditMemos->map(fn ($c) => [
        $c->provider->business_name ?? '—', $c->tran_id, $c->tran_date?->format('d/m/Y'),
        (float) $c->amount, (float) $c->amount_remaining,
    ])->toArray());

    $spreadsheet->setActiveSheetIndex(0);

    $suffix = !empty($validated['provider_id'])
        ? '-' . str(\App\Models\Provider::find($validated['provider_id'])->business_name)->slug()
        : '-todos';
    $filename = 'reporte-cuentas-por-cobrar' . $suffix . '-' . now()->format('Y-m-d') . '.xlsx';

    return response()->streamDownload(function () use ($spreadsheet) {
        (new Xlsx($spreadsheet))->save('php://output');
    }, $filename, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}
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