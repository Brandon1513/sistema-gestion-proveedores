<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Provider;
use Illuminate\Http\Request;
use App\Models\ProviderDocument;
use Illuminate\Http\JsonResponse;
use App\Mail\DocumentValidatedMail;
use App\Models\DocumentValidation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;

class DocumentValidationController extends Controller
{
    /**
     * Listar documentos pendientes de validación
     */
    public function pending(Request $request): JsonResponse
    {
        $query = ProviderDocument::with([
            'provider.providerType',
            'documentType',
            'uploadedBy'
        ])
        ->where('status', 'pending');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('provider', function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('rfc', 'like', "%{$search}%");
            });
        }

        if ($request->has('provider_type') && $request->provider_type != '') {
            $query->whereHas('provider', function ($q) use ($request) {
                $q->where('provider_type_id', $request->provider_type);
            });
        }

        if ($request->has('document_type') && $request->document_type != '') {
            $query->whereHas('documentType', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->document_type}%");
            });
        }

        $documents = $query->latest()->get();

        $documents = $documents->map(function ($doc) {
            $doc->days_until_expiry = null;
            if ($doc->expiry_date) {
                $expiryDate = Carbon::parse($doc->expiry_date);
                $today = Carbon::now();
                $doc->days_until_expiry = $today->diffInDays($expiryDate, false);
            }
            return $doc;
        });

        $stats = [
            'total_pending' => $documents->count(),
            'urgent' => $documents->filter(function ($doc) {
                return $doc->days_until_expiry !== null && $doc->days_until_expiry <= 7 && $doc->days_until_expiry >= 0;
            })->count(),
            'expiring_soon' => $documents->filter(function ($doc) {
                return $doc->days_until_expiry !== null &&
                       $doc->days_until_expiry > 7 &&
                       $doc->days_until_expiry <= 15;
            })->count(),
        ];

        return response()->json([
            'documents' => $documents,
            'stats' => $stats,
        ]);
    }

    /**
     * Validar documento (aprobar o rechazar)
     * ✅ Con activación/desactivación automática del proveedor
     */
    public function validate(Request $request, $providerId, $documentId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'comments' => 'required_if:status,rejected|nullable|string|max:1000',
        ], [
            'status.required' => 'Debe especificar un estado (approved o rejected)',
            'status.in' => 'El estado debe ser "approved" o "rejected"',
            'comments.required_if' => 'Los comentarios son obligatorios al rechazar un documento',
        ]);

        try {
            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $providerId)
                ->firstOrFail();

            $document->update(['status' => $validated['status']]);

            $validation = DocumentValidation::create([
                'provider_document_id' => $document->id,
                'validated_by' => auth()->id(),
                'action' => $validated['status'],
                'comments' => $validated['comments'] ?? null,
                'validated_at' => now(),
            ]);

            $document->load(['documentType', 'provider.providerType', 'uploadedBy']);
            $validation->load('validatedBy');

            $provider = $document->provider;
            $providerActivated = false;

            // ✅ Aprobación: verificar si se activa el proveedor
            if ($validated['status'] === 'approved' && $provider->status === 'pending') {
                $providerActivated = $this->checkAndActivateProvider($provider);
            }

            // ✅ Rechazo: si el proveedor estaba activo, regresa a pendiente
            if ($validated['status'] === 'rejected' && $provider->status === 'active') {
                $provider->update(['status' => 'pending']);
                \Log::info("Proveedor {$provider->id} ({$provider->business_name}) regresó a pendiente por documento rechazado");
            }

            // 📧 Email al proveedor
            try {
                if ($provider->email) {
                    Mail::to($provider->email)->send(
                        new DocumentValidatedMail($document, $validation)
                    );
                    \Log::info("Email de validación enviado a: {$provider->email} - Status: {$validated['status']}");
                }
            } catch (\Exception $e) {
                \Log::error('Error al enviar email de validación: ' . $e->getMessage());
            }

            return response()->json([
                'message' => $validated['status'] === 'approved'
                    ? 'Documento aprobado exitosamente'
                    : 'Documento rechazado',
                'document' => $document,
                'validation' => $validation,
                'provider_activated' => $providerActivated,
                'provider_status' => $provider->fresh()->status,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al validar el documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Verifica si todos los documentos requeridos tienen al menos uno aprobado.
     *    Si es así, activa al proveedor automáticamente.
     */
    private function checkAndActivateProvider(Provider $provider): bool
    {
        $requiredDocTypeIds = $provider->providerType
            ->documentTypes()
            ->wherePivot('is_required', true)
            ->pluck('document_types.id');

        if ($requiredDocTypeIds->isEmpty()) {
            \Log::warning("Proveedor {$provider->id}: sin documentos requeridos configurados para su tipo");
            return false;
        }

        foreach ($requiredDocTypeIds as $docTypeId) {
            $hasApproved = ProviderDocument::where('provider_id', $provider->id)
                ->where('document_type_id', $docTypeId)
                ->where('status', 'approved')
                ->exists();

            if (!$hasApproved) {
                return false;
            }
        }

        $provider->update(['status' => 'active']);
        \Log::info("Proveedor {$provider->id} ({$provider->business_name}) activado automáticamente");

        return true;
    }

    /**
     * Historial de validaciones de un documento
     */
    public function history(Request $request, $documentId): JsonResponse
    {
        try {
            $user = $request->user();
            $document = ProviderDocument::with(['documentType', 'provider'])->findOrFail($documentId);

            if ($user->hasRole('proveedor')) {
                $provider = Provider::where('email', $user->email)->first();
                if (!$provider || $document->provider_id !== $provider->id) {
                    return response()->json([
                        'message' => 'No tiene permisos para ver este historial'
                    ], 403);
                }
            }

            $validations = DocumentValidation::with(['validatedBy:id,name,email'])
                ->where('provider_document_id', $documentId)
                ->latest('validated_at')
                ->get()
                ->map(function ($validation) {
                    return [
                        'id' => $validation->id,
                        'status' => $validation->action,
                        'comments' => $validation->comments,
                        'validated_by' => [
                            'id' => $validation->validatedBy->id,
                            'name' => $validation->validatedBy->name,
                            'email' => $validation->validatedBy->email,
                        ],
                        'validated_at' => $validation->validated_at->toIso8601String(),
                        'created_at' => $validation->validated_at->toDateTimeString(),
                    ];
                });

            return response()->json([
                'document' => [
                    'id' => $document->id,
                    'document_type' => $document->documentType->name ?? 'Desconocido',
                    'file_name' => $document->file_name,
                    'current_status' => $document->status,
                    'provider' => [
                        'id' => $document->provider->id,
                        'business_name' => $document->provider->business_name,
                    ],
                ],
                'validations' => $validations,
                'total_validations' => $validations->count(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener historial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}