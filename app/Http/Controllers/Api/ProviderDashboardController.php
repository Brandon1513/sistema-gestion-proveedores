<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProviderDashboardController extends Controller
{
    /**
     * Obtener estadísticas del proveedor autenticado
     */
    public function stats(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $documents = ProviderDocument::where('provider_id', $provider->id)->get();

        $stats = [
            'total'    => $documents->count(),
            'approved' => $documents->where('status', 'approved')->count(),
            'pending'  => $documents->where('status', 'pending')->count(),
            'rejected' => $documents->where('status', 'rejected')->count(),
            'expiring' => $documents->where('status', 'approved')
                ->filter(function ($doc) {
                    if (!$doc->expiry_date) return false;
                    $daysUntilExpiry = Carbon::parse($doc->expiry_date)->diffInDays(Carbon::today());
                    return $daysUntilExpiry <= 30;
                })
                ->count(),
        ];

        return response()->json(['stats' => $stats]);
    }

    /**
     * Obtener documentos del proveedor autenticado
     */
    public function documents(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $documents = ProviderDocument::with('documentType')
            ->where('provider_id', $provider->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($document) {
                $daysUntilExpiry = null;
                if ($document->expiry_date) {
                    $daysUntilExpiry = Carbon::parse($document->expiry_date)
                        ->diffInDays(Carbon::today(), false);
                }

                return [
                    'id'               => $document->id,
                    'document_type'    => $document->documentType,
                    'file_name'        => $document->original_filename,
                    'file_path'        => $document->file_path,
                    'status'           => $document->status,
                    'issue_date'       => $document->issue_date,
                    'expiry_date'      => $document->expiry_date,
                    'product_name'     => $document->product_name,
                    'days_until_expiry'=> $daysUntilExpiry,
                    'uploaded_at'      => $document->created_at,
                ];
            });

        return response()->json(['documents' => $documents]);
    }

    /**
     * Obtener documentos requeridos y recomendados según el tipo de proveedor.
     *
     * Para documentos con allows_multiple = true devuelve:
     *   - uploaded: bool (hay al menos uno)
     *   - documents: array con todos los archivos cargados (con product_name)
     *   - uploaded_document: null
     *
     * Para documentos con allows_multiple = false devuelve:
     *   - uploaded: bool
     *   - documents: []
     *   - uploaded_document: objeto con el último archivo aprobado o pendiente
     *
     * is_required viene del pivot (document_type_provider_type.is_required),
     * NO del campo global document_types.is_required.
     */
    public function requiredDocuments(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::with('providerType')->where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        // ── Todos los tipos de documento asignados a este tipo de proveedor ──
        // withPivot para leer is_required desde la tabla pivot
        $docTypes = DocumentType::whereHas('providerTypes', function ($q) use ($provider) {
                $q->where('provider_type_id', $provider->provider_type_id);
            })
            ->with(['providerTypes' => function ($q) use ($provider) {
                $q->where('provider_type_id', $provider->provider_type_id);
            }])
            ->get();

        // ── Todos los documentos cargados por el proveedor (sin filtrar status) ──
        $allUploaded = ProviderDocument::with('documentType')
            ->where('provider_id', $provider->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar por document_type_id para acceso rápido
        $groupedByType = $allUploaded->groupBy('document_type_id');

        // ── Mapear cada tipo de documento ────────────────────────────────────
        $documentsWithStatus = $docTypes->map(function ($docType) use ($groupedByType, $provider) {
            // is_required viene del pivot, no del campo global
            $pivotIsRequired = (bool) optional($docType->providerTypes->first())->pivot->is_required;

            $docsOfThisType = $groupedByType->get($docType->id, collect());

            if ($docType->allows_multiple) {
                // ── Múltiples cargas: devolver array completo ─────────────
                $docsArray = $docsOfThisType->map(fn($d) => $this->formatDocument($d))->values();

                return [
                    'id'               => $docType->id,
                    'name'             => $docType->name,
                    'description'      => $docType->description,
                    'category'         => $docType->category,
                    'is_required'      => $pivotIsRequired,
                    'allows_multiple'  => true,
                    'requires_expiry'  => (bool) $docType->requires_expiry,
                    'uploaded'         => $docsArray->isNotEmpty(),
                    'uploaded_document'=> null,
                    'documents'        => $docsArray,
                ];
            } else {
                // ── Carga única: devolver el documento más relevante ──────
                // Prioridad: pendiente > aprobado > rechazado > el más reciente
                $best = $docsOfThisType->firstWhere('status', 'pending')
                    ?? $docsOfThisType->firstWhere('status', 'approved')
                    ?? $docsOfThisType->firstWhere('status', 'rejected')
                    ?? $docsOfThisType->first();

                return [
                    'id'               => $docType->id,
                    'name'             => $docType->name,
                    'description'      => $docType->description,
                    'category'         => $docType->category,
                    'is_required'      => $pivotIsRequired,
                    'allows_multiple'  => false,
                    'requires_expiry'  => (bool) $docType->requires_expiry,
                    'uploaded'         => $best !== null,
                    'uploaded_document'=> $best ? $this->formatDocument($best) : null,
                    'documents'        => [],
                ];
            }
        });

        return response()->json([
            'provider_type'      => $provider->providerType,
            'required_documents' => $documentsWithStatus->values(),
        ]);
    }

    /**
     * Obtener documentos próximos a vencer
     */
    public function expiringDocuments(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $today = Carbon::today();

        $expiringDocuments = ProviderDocument::with('documentType')
            ->where('provider_id', $provider->id)
            ->where('status', 'approved')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $today)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(30))
            ->get()
            ->map(function ($document) use ($today) {
                $expiryDate = Carbon::parse($document->expiry_date);
                $daysLeft   = $today->diffInDays($expiryDate);

                return [
                    'id'           => $document->id,
                    'name'         => $document->documentType->name,
                    'product_name' => $document->product_name,
                    'file_name'    => $document->original_filename,
                    'expiry_date'  => $document->expiry_date,
                    'days_left'    => $daysLeft,
                    'urgency'      => $daysLeft <= 7 ? 'critical' : ($daysLeft <= 15 ? 'warning' : 'notice'),
                ];
            });

        return response()->json(['expiring_documents' => $expiringDocuments]);
    }

    // ─── Helper privado ───────────────────────────────────────────────────────

    /**
     * Formato consistente para un ProviderDocument en las respuestas del portal.
     */
    private function formatDocument(ProviderDocument $doc): array
    {
        return [
            'id'                => $doc->id,
            'status'            => $doc->status,
            'original_filename' => $doc->original_filename,
            'file_extension'    => $doc->file_extension,
            'file_size_kb'      => $doc->file_size_kb,
            'issue_date'        => $doc->issue_date,
            'expiry_date'       => $doc->expiry_date,
            'product_name'      => $doc->product_name,
            'notes'             => $doc->notes,
            'version'           => $doc->version,
            'uploaded_at'       => $doc->created_at,
            // Relación con documentType por si el frontend la necesita
            'document_type'     => $doc->documentType
                ? ['id' => $doc->documentType->id, 'name' => $doc->documentType->name]
                : null,
        ];
    }
}