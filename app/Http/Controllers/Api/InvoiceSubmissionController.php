<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderInvoiceSubmission;
use App\Models\User;
use App\Notifications\InvoiceSubmissionNotification;
use App\Services\CfdiParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InvoiceSubmissionController extends Controller
{
    protected const ACCEPTED_VERSIONS = ['4.0'];

    public function __construct(protected CfdiParser $parser)
    {
    }

    // ═══════════════ PROVEEDOR ═══════════════

    protected function currentProvider(Request $request): Provider
    {
        return Provider::where('email', $request->user()->email)->firstOrFail();
    }

    public function providerIndex(Request $request): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $submissions = ProviderInvoiceSubmission::where('provider_id', $provider->id)
            ->with('netsuiteInvoice:id,tran_id,status')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['submissions' => $submissions]);
    }

    public function providerStore(Request $request): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'xml' => ['required', 'file', 'extensions:xml', 'max:2048'],
        ]);

        // Que sea un PDF de verdad, no solo la extensión.
        $pdfHeader = file_get_contents($request->file('pdf')->getRealPath(), false, null, 0, 5);
        if ($pdfHeader !== '%PDF-') {
            return $this->cfdiError('INVALID_PDF', 'El archivo PDF está dañado o no es un PDF válido.');
        }

        try {
            $cfdi = $this->parser->parse(file_get_contents($request->file('xml')->getRealPath()));
        } catch (\InvalidArgumentException $e) {
            return $this->cfdiError('INVALID_XML', $e->getMessage());
        }

        if (!in_array($cfdi['version'], self::ACCEPTED_VERSIONS, true)) {
            return $this->cfdiError('UNSUPPORTED_VERSION', 'Solo se aceptan facturas CFDI versión 4.0.');
        }

        if ($cfdi['tipo'] !== 'I') {
            return $this->cfdiError('NOT_INVOICE_TYPE', 'El XML no es una factura (CFDI de tipo Ingreso).');
        }

        if ($cfdi['issuer_rfc'] !== strtoupper(trim((string) $provider->rfc))) {
            return $this->cfdiError(
                'ISSUER_MISMATCH',
                "El RFC emisor del CFDI ({$cfdi['issuer_rfc']}) no coincide con el RFC de tu cuenta ({$provider->rfc})."
            );
        }

        $companyRfc = strtoupper(trim((string) config('services.company.rfc')));
        if ($companyRfc === '') {
            Log::warning('COMPANY_RFC no está configurado; se omite la validación del RFC receptor.');
        } elseif ($cfdi['receiver_rfc'] !== $companyRfc) {
            return $this->cfdiError(
                'RECEIVER_MISMATCH',
                "El RFC receptor del CFDI ({$cfdi['receiver_rfc']}) no corresponde a DASAVENA. Verifica los datos de facturación."
            );
        }

        $existing = ProviderInvoiceSubmission::where('uuid', $cfdi['uuid'])
            ->whereNotIn('status', [
                ProviderInvoiceSubmission::STATUS_REJECTED,
                ProviderInvoiceSubmission::STATUS_CANCELLED,
            ])
            ->first();

        if ($existing) {
            $message = $existing->provider_id === $provider->id
                ? "Este CFDI ya fue subido el {$existing->created_at->format('d/m/Y')} (estatus: {$this->statusLabel($existing->status)})."
                : 'Este CFDI (UUID) ya fue registrado en el sistema.';

            return $this->cfdiError('DUPLICATE_UUID', $message, 409);
        }

        $dir = "invoice-submissions/{$provider->id}";
        $stamp = now()->format('YmdHis');
        $pdfPath = $request->file('pdf')->storeAs($dir, "{$cfdi['uuid']}_{$stamp}.pdf", 'local');
        $xmlPath = $request->file('xml')->storeAs($dir, "{$cfdi['uuid']}_{$stamp}.xml", 'local');

        try {
            $submission = ProviderInvoiceSubmission::create([
                'provider_id' => $provider->id,
                'uploaded_by' => $request->user()->id,
                'uuid' => $cfdi['uuid'],
                'serie' => $cfdi['serie'],
                'folio' => $cfdi['folio'],
                'issued_at' => $cfdi['issued_at'],
                'total' => $cfdi['total'],
                'currency' => $cfdi['currency'],
                'payment_method' => $cfdi['payment_method'],
                'issuer_rfc' => $cfdi['issuer_rfc'],
                'receiver_rfc' => $cfdi['receiver_rfc'],
                'pdf_path' => $pdfPath,
                'xml_path' => $xmlPath,
                'status' => ProviderInvoiceSubmission::STATUS_SUBMITTED,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dos subidas simultáneas del mismo UUID: gana la primera.
            Storage::disk('local')->delete([$pdfPath, $xmlPath]);

            return $this->cfdiError('DUPLICATE_UUID', 'Este CFDI ya fue registrado en el sistema.', 409);
        }

        Log::info('Factura subida por proveedor', [
            'submission_id' => $submission->id,
            'provider_id' => $provider->id,
            'user_id' => $request->user()->id,
            'uuid' => $submission->uuid,
        ]);

        $this->notifyAccountsPayable($submission);

        return response()->json([
            'message' => 'Factura recibida correctamente',
            'submission' => $submission,
        ], 201);
    }

    public function providerDownload(Request $request, int $id, string $kind)
    {
        $provider = $this->currentProvider($request);
        $submission = ProviderInvoiceSubmission::where('provider_id', $provider->id)->findOrFail($id);

        return $this->streamFile($request, $submission, $kind);
    }

    // ═══════════════ CUENTAS POR PAGAR / FINANZAS / COMPRAS ═══════════════

    public function financeIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                ProviderInvoiceSubmission::STATUS_SUBMITTED,
                ProviderInvoiceSubmission::STATUS_CAPTURED,
                ProviderInvoiceSubmission::STATUS_REJECTED,
            ])],
            'provider_id' => ['nullable', 'exists:providers,id'],
        ]);

        $query = ProviderInvoiceSubmission::with('provider:id,business_name,rfc')
            ->orderByDesc('created_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return response()->json($query->paginate(200));
    }

    public function financeReview(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ProviderInvoiceSubmission::STATUS_CAPTURED,
                ProviderInvoiceSubmission::STATUS_REJECTED,
                ProviderInvoiceSubmission::STATUS_CANCELLED,
            ])],
            'review_notes' => ['required_if:status,rejected', 'required_if:status,cancelled', 'nullable', 'string', 'max:2000'],
        ]);

        $submission = ProviderInvoiceSubmission::with('provider')->findOrFail($id);
        $target = $validated['status'];

        if ($target === ProviderInvoiceSubmission::STATUS_CANCELLED) {
            // Cancelar es válido desde 'submitted' o 'captured' — pero no
            // desde un estatus ya final (rejected/cancelled).
            if (in_array($submission->status, [
                ProviderInvoiceSubmission::STATUS_CANCELLED,
                ProviderInvoiceSubmission::STATUS_REJECTED,
            ], true)) {
                return response()->json(['message' => 'Esta factura ya no puede cancelarse.'], 409);
            }
        } else {
            // Aprobar/rechazar solo aplica a facturas aún sin revisar.
            if ($submission->status !== ProviderInvoiceSubmission::STATUS_SUBMITTED) {
                return response()->json(['message' => 'Esta factura ya fue revisada.'], 409);
            }
        }

        $submission->update([
            'status' => $target,
            'review_notes' => $validated['review_notes'] ?? $submission->review_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'captured_at' => $target === ProviderInvoiceSubmission::STATUS_CAPTURED ? now() : $submission->captured_at,
        ]);

        $this->notifyProvider($submission, $target);

        return response()->json([
            'message' => 'Factura actualizada',
            'submission' => $submission->fresh('provider:id,business_name,rfc'),
        ]);
    }

    public function financeDownload(Request $request, int $id, string $kind)
    {
        $submission = ProviderInvoiceSubmission::findOrFail($id);

        return $this->streamFile($request, $submission, $kind);
    }

    // ═══════════════ HELPERS ═══════════════

    protected function streamFile(Request $request, ProviderInvoiceSubmission $submission, string $kind)
    {
        abort_unless(in_array($kind, ['pdf', 'xml'], true), 404);

        $path = $kind === 'pdf' ? $submission->pdf_path : $submission->xml_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Archivo no encontrado');

        Log::info('Descarga de factura subida', [
            'submission_id' => $submission->id,
            'kind' => $kind,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        $name = 'factura-' . ($submission->folio ?: substr($submission->uuid, 0, 8)) . ".{$kind}";

        return Storage::disk('local')->download($path, $name);
    }

    protected function cfdiError(string $code, string $message, int $status = 422): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            ProviderInvoiceSubmission::STATUS_CAPTURED => 'capturada',
            ProviderInvoiceSubmission::STATUS_REJECTED => 'rechazada',
            ProviderInvoiceSubmission::STATUS_CANCELLED => 'cancelada',
            default => 'recibida',
        };
    }

    protected function notifyAccountsPayable(ProviderInvoiceSubmission $submission): void
    {
        try {
            $recipients = User::role('cuentas_por_pagar')->where('is_active', true)->get();

            if ($recipients->isEmpty()) {
                Log::warning('Factura subida sin usuarios en cuentas_por_pagar; se notifica a finanzas', [
                    'submission_id' => $submission->id,
                ]);
                $recipients = User::role('finanzas')->where('is_active', true)->get();
            }

            foreach ($recipients as $user) {
                $user->notify(new InvoiceSubmissionNotification($submission, 'submitted'));
            }
        } catch (\Throwable $e) {
            // Un fallo de correo no debe tumbar la subida: la factura ya quedó guardada.
            Log::error('No se pudo notificar a cuentas por pagar', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyProvider(ProviderInvoiceSubmission $submission, string $event): void
    {
        try {
            $user = User::where('email', $submission->provider->email)->first();
            $user?->notify(new InvoiceSubmissionNotification($submission, $event));
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar al proveedor', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}