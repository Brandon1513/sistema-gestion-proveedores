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

        // Filtros
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

        // Obtener documentos
        $documents = $query->latest()->get();

        // Calcular días hasta vencimiento para cada documento
        $documents = $documents->map(function ($doc) {
            $doc->days_until_expiry = null;
            
            if ($doc->expiry_date) {
                $expiryDate = Carbon::parse($doc->expiry_date);
                $today = Carbon::now();
                
                // Días hasta vencer (negativo si ya venció)
                $doc->days_until_expiry = $today->diffInDays($expiryDate, false);
            }
            
            return $doc;
        });

        // Calcular estadísticas
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
            // Buscar el documento
            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $providerId)
                ->firstOrFail();

            // Actualizar estado del documento
            $document->update([
                'status' => $validated['status'],
            ]);

            // Crear registro de validación
            $validation = DocumentValidation::create([
                'provider_document_id' => $document->id,
                'validated_by' => auth()->id(),
                'action' => $validated['status'],
                'comments' => $validated['comments'] ?? null,
                'validated_at' => now(),
            ]);

            // Cargar relaciones necesarias
            $document->load(['documentType', 'provider.providerType', 'uploadedBy']);
            $validation->load('validatedBy');

            // 📧 ENVIAR NOTIFICACIÓN POR EMAIL AL PROVEEDOR
            try {
                $providerEmail = $document->provider->email;

                if ($providerEmail) {
                    Mail::to($providerEmail)->send(
                        new DocumentValidatedMail($document, $validation)
                    );
                    
                    \Log::info("Email de validación enviado a: {$providerEmail} - Status: {$validated['status']}");
                } else {
                    \Log::warning("Proveedor {$document->provider->id} no tiene email registrado");
                }
            } catch (\Exception $e) {
                \Log::error('Error al enviar email de validación: ' . $e->getMessage());
                // No lanzar excepción para no interrumpir el flujo
            }
            
            return response()->json([
                'message' => $validated['status'] === 'approved' 
                    ? 'Documento aprobado exitosamente' 
                    : 'Documento rechazado',
                'document' => $document,
                'validation' => $validation,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Documento no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al validar el documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Historial de validaciones de un documento
     * Accesible por: Admin, Calidad, y Proveedor (solo sus documentos)
     */
    public function history(Request $request, $documentId): JsonResponse
    {
        try {
            $user = $request->user();
            $document = ProviderDocument::with(['documentType', 'provider'])->findOrFail($documentId);
            
            // ⭐ CONTROL DE PERMISOS POR ROL
            if ($user->hasRole('proveedor')) {
                // El proveedor solo puede ver historial de sus propios documentos
                $provider = Provider::where('email', $user->email)->first();
                
                if (!$provider || $document->provider_id !== $provider->id) {
                    return response()->json([
                        'message' => 'No tiene permisos para ver este historial'
                    ], 403);
                }
            }
            // Admin y Calidad pueden ver cualquier historial (no necesitan validación)
            
            // Obtener validaciones con información del usuario que validó
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
            return response()->json([
                'message' => 'Documento no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener historial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}