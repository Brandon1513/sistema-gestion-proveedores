<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetsuiteVendorPayment;
use App\Models\Provider;
use App\Models\ProviderPaymentComplement;
use App\Models\User;
use App\Notifications\PaymentComplementNotification;
use App\Services\CfdiParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentComplementController extends Controller
{
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

        $complements = ProviderPaymentComplement::where('provider_id', $provider->id)
            ->with('payment:id,tran_id,amount')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['complements' => $complements]);
    }

    public function providerStore(Request $request): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $request->validate([
            'netsuite_vendor_payment_id' => ['required', 'integer'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'xml' => ['required', 'file', 'extensions:xml', 'max:2048'],
        ]);

        $payment = NetsuiteVendorPayment::where('provider_id', $provider->id)
            ->find($request->netsuite_vendor_payment_id);

        if (!$payment) {
            return $this->cfdiError('PAYMENT_NOT_FOUND', 'No se encontró ese pago en tu cuenta.', 404);
        }

        $existing = ProviderPaymentComplement::where('netsuite_vendor_payment_id', $payment->id)
            ->where('status', '!=', ProviderPaymentComplement::STATUS_REJECTED)
            ->first();

        if ($existing) {
            return $this->cfdiError('COMPLEMENT_ALREADY_EXISTS', 'Este pago ya tiene un complemento subido.', 409);
        }

        $pdfHeader = file_get_contents($request->file('pdf')->getRealPath(), false, null, 0, 5);
        if ($pdfHeader !== '%PDF-') {
            return $this->cfdiError('INVALID_PDF', 'El archivo PDF está dañado o no es un PDF válido.');
        }

        try {
            $cfdi = $this->parser->parse(file_get_contents($request->file('xml')->getRealPath()));
        } catch (\InvalidArgumentException $e) {
            return $this->cfdiError('INVALID_XML', $e->getMessage());
        }

        if ($cfdi['tipo'] !== 'P') {
            return $this->cfdiError('NOT_PAYMENT_TYPE', 'El XML no es un Complemento de Pago (CFDI de tipo Pago).');
        }

        if ($cfdi['issuer_rfc'] !== strtoupper(trim((string) $provider->rfc))) {
            return $this->cfdiError(
                'ISSUER_MISMATCH',
                "El RFC emisor del CFDI ({$cfdi['issuer_rfc']}) no coincide con el RFC de tu cuenta ({$provider->rfc})."
            );
        }

        $companyRfc = strtoupper(trim((string) config('services.company.rfc')));
        if ($companyRfc !== '' && $cfdi['receiver_rfc'] !== $companyRfc) {
            return $this->cfdiError(
                'RECEIVER_MISMATCH',
                "El RFC receptor del CFDI ({$cfdi['receiver_rfc']}) no corresponde a DASAVENA."
            );
        }

        $duplicateUuid = ProviderPaymentComplement::where('uuid', $cfdi['uuid'])
            ->where('status', '!=', ProviderPaymentComplement::STATUS_REJECTED)
            ->exists();

        if ($duplicateUuid) {
            return $this->cfdiError('DUPLICATE_UUID', 'Este CFDI ya fue registrado en el sistema.', 409);
        }

        $dir = "payment-complements/{$provider->id}";
        $stamp = now()->format('YmdHis');
        $pdfPath = $request->file('pdf')->storeAs($dir, "{$cfdi['uuid']}_{$stamp}.pdf", 'local');
        $xmlPath = $request->file('xml')->storeAs($dir, "{$cfdi['uuid']}_{$stamp}.xml", 'local');

        try {
            $complement = ProviderPaymentComplement::create([
                'provider_id' => $provider->id,
                'netsuite_vendor_payment_id' => $payment->id,
                'uploaded_by' => $request->user()->id,
                'uuid' => $cfdi['uuid'],
                'issued_at' => $cfdi['issued_at'],
                'total' => $cfdi['total'] ?: null,
                'currency' => $cfdi['currency'],
                'issuer_rfc' => $cfdi['issuer_rfc'],
                'receiver_rfc' => $cfdi['receiver_rfc'],
                'pdf_path' => $pdfPath,
                'xml_path' => $xmlPath,
                'status' => ProviderPaymentComplement::STATUS_SUBMITTED,
            ]);
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete([$pdfPath, $xmlPath]);

            return $this->cfdiError('DUPLICATE_UUID', 'Este CFDI ya fue registrado en el sistema.', 409);
        }

        Log::info('Complemento de pago subido por proveedor', [
            'complement_id' => $complement->id,
            'provider_id' => $provider->id,
            'payment_id' => $payment->id,
            'uuid' => $complement->uuid,
        ]);

        $this->notifyAccountsPayable($complement);

        return response()->json([
            'message' => 'Complemento de pago recibido correctamente',
            'complement' => $complement,
        ], 201);
    }

    public function providerDownload(Request $request, int $id, string $kind)
    {
        $provider = $this->currentProvider($request);
        $complement = ProviderPaymentComplement::where('provider_id', $provider->id)->findOrFail($id);

        return $this->streamFile($request, $complement, $kind);
    }

    // ═══════════════ CUENTAS POR PAGAR / FINANZAS ═══════════════

    public function financeIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                ProviderPaymentComplement::STATUS_SUBMITTED,
                ProviderPaymentComplement::STATUS_APPROVED,
                ProviderPaymentComplement::STATUS_REJECTED,
            ])],
            'provider_id' => ['nullable', 'exists:providers,id'],
        ]);

        $query = ProviderPaymentComplement::with(['provider:id,business_name,rfc', 'payment:id,tran_id,amount'])
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
                ProviderPaymentComplement::STATUS_APPROVED,
                ProviderPaymentComplement::STATUS_REJECTED,
            ])],
            'review_notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        $complement = ProviderPaymentComplement::with('provider')->findOrFail($id);

        if ($complement->status !== ProviderPaymentComplement::STATUS_SUBMITTED) {
            return response()->json(['message' => 'Este complemento ya fue revisado.'], 409);
        }

        $complement->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyProvider($complement, $validated['status']);

        return response()->json([
            'message' => 'Complemento actualizado',
            'complement' => $complement->fresh(['provider:id,business_name,rfc', 'payment:id,tran_id,amount']),
        ]);
    }

    public function financeDownload(Request $request, int $id, string $kind)
    {
        $complement = ProviderPaymentComplement::findOrFail($id);

        return $this->streamFile($request, $complement, $kind);
    }

    // ═══════════════ HELPERS ═══════════════

    protected function streamFile(Request $request, ProviderPaymentComplement $complement, string $kind)
    {
        abort_unless(in_array($kind, ['pdf', 'xml'], true), 404);

        $path = $kind === 'pdf' ? $complement->pdf_path : $complement->xml_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Archivo no encontrado');

        Log::info('Descarga de complemento de pago', [
            'complement_id' => $complement->id,
            'kind' => $kind,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        $name = 'complemento-pago-' . substr($complement->uuid, 0, 8) . ".{$kind}";

        return Storage::disk('local')->download($path, $name);
    }

    protected function cfdiError(string $code, string $message, int $status = 422): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }

    protected function notifyAccountsPayable(ProviderPaymentComplement $complement): void
    {
        try {
            $recipients = User::role('cuentas_por_pagar')->where('is_active', true)->get();

            if ($recipients->isEmpty()) {
                $recipients = User::role('finanzas')->where('is_active', true)->get();
            }

            foreach ($recipients as $user) {
                $user->notify(new PaymentComplementNotification($complement, 'submitted'));
            }
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar a cuentas por pagar (complemento)', [
                'complement_id' => $complement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyProvider(ProviderPaymentComplement $complement, string $event): void
    {
        try {
            $user = User::where('email', $complement->provider->email)->first();
            $user?->notify(new PaymentComplementNotification($complement, $event));
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar al proveedor (complemento)', [
                'complement_id' => $complement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}