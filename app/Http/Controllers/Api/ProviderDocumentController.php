<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProviderDocumentController extends Controller
{
    /**
     * Lista de documentos de un proveedor
     */
    public function index(Provider $provider): JsonResponse
    {
        $documents = $provider->documents()
            ->with(['documentType', 'uploadedBy', 'validations.validatedBy'])
            ->latest()
            ->get();

        return response()->json([
            'documents' => $documents,
        ]);
    }

    /**
     * Documentos requeridos para un proveedor (vista interna — Calidad/Compras)
     */
    public function required(Provider $provider): JsonResponse
    {
        $allDocTypes = $provider->providerType
            ->documentTypes()
            ->withPivot('is_required')
            ->get();

        $uploadedDocuments = $provider->documents()
            ->whereIn('document_type_id', $allDocTypes->pluck('id'))
            ->where('status', 'approved')
            ->latest('version')
            ->get()
            ->groupBy('document_type_id');

        $documents = $allDocTypes->map(function ($docType) use ($uploadedDocuments) {
            $docs = $uploadedDocuments->get($docType->id, collect());

            return [
                'document_type' => $docType,
                'is_required'   => (bool) $docType->pivot->is_required,
                'allows_multiple' => (bool) $docType->allows_multiple,
                // Para docs de carga única devolvemos el primero; para múltiples, todos
                'uploaded'      => $docs->isNotEmpty(),
                'document'      => $docType->allows_multiple ? null : $docs->first(),
                'documents'     => $docType->allows_multiple ? $docs->values() : [],
            ];
        });

        $requiredDocs   = $documents->where('is_required', true);
        $totalRequired  = $requiredDocs->count();
        $totalUploaded  = $requiredDocs->filter(fn($d) => $d['uploaded'])->count();

        return response()->json([
            'required_documents'   => $documents,
            'total_required'       => $totalRequired,
            'total_uploaded'       => $totalUploaded,
            'completion_percentage' => $totalRequired > 0
                ? round(($totalUploaded / $totalRequired) * 100, 2)
                : 0,
        ]);
    }

    /**
     * Subir documento
     *
     * Comportamiento según allows_multiple del tipo de documento:
     *  - false: versionado (el nuevo reemplaza al anterior pero se conserva historial)
     *  - true:  se agrega un registro nuevo por cada producto (product_name obligatorio)
     */
    public function store(Request $request, Provider $provider): JsonResponse
    {
        // ─── Tipos que requieren product_name ─────────────────────────────────
        // Se determina dinámicamente en base al tipo de proveedor
        $providerTypeCode = $provider->providerType->code ?? '';
        $typesWithProductName = ['mp_me', 'sustancias_quimicas', 'insumos', 'control_plagas'];
        $requiresProductName  = in_array($providerTypeCode, $typesWithProductName);

        $validated = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file'             => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'issue_date'       => 'nullable|date',
            'expiry_date'      => 'nullable|date|after:today',
            'notes'            => 'nullable|string|max:1000',
            // product_name: obligatorio solo cuando el tipo de documento permite múltiples
            // y el proveedor es de un tipo que lo requiere
            'product_name'     => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request, $requiresProductName) {
                    $docType = DocumentType::find($request->document_type_id);
                    if ($docType && $docType->allows_multiple && $requiresProductName && empty($value)) {
                        $fail('El nombre del producto es obligatorio para este tipo de documento.');
                    }
                },
            ],
        ], [
            'file.max'              => 'El archivo no debe superar los 10MB',
            'file.mimes'            => 'Solo se permiten archivos PDF, imágenes, Word y Excel',
            'expiry_date.after'     => 'La fecha de vencimiento debe ser posterior a hoy',
        ]);

        try {
            $file         = $request->file('file');
            $documentType = DocumentType::findOrFail($validated['document_type_id']);

            // ─── Verificar que el tipo de documento pertenece al tipo del proveedor ──
            $belongsToType = $provider->providerType
                ->documentTypes()
                ->where('document_types.id', $documentType->id)
                ->exists();

            if (!$belongsToType) {
                return response()->json([
                    'message' => 'Este tipo de documento no corresponde a la categoría del proveedor.',
                ], 422);
            }

            // ─── Guardar archivo ──────────────────────────────────────────────
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs(
                'providers/' . $provider->id . '/documents',
                $fileName,
                'documents'
            );

            // ─── Calcular versión ─────────────────────────────────────────────
            // Para allows_multiple: cada carga es independiente (version siempre 1)
            // Para carga única: incrementar versión sobre el documento anterior
            if ($documentType->allows_multiple) {
                $version = 1;
            } else {
                $lastVersion = ProviderDocument::where('provider_id', $provider->id)
                    ->where('document_type_id', $documentType->id)
                    ->max('version') ?? 0;
                $version = $lastVersion + 1;
            }

            // ─── Crear registro ───────────────────────────────────────────────
            $document = $provider->documents()->create([
                'document_type_id' => $documentType->id,
                'file_path'        => $filePath,
                'original_filename'=> $file->getClientOriginalName(),
                'file_extension'   => $file->getClientOriginalExtension(),
                'file_size_kb'     => round($file->getSize() / 1024),
                'issue_date'       => $validated['issue_date'] ?? null,
                'expiry_date'      => $validated['expiry_date'] ?? null,
                'status'           => 'pending',
                'notes'            => $validated['notes'] ?? null,
                'product_name'     => $validated['product_name'] ?? null,
                'version'          => $version,
                'uploaded_by'      => auth()->id(),
            ]);

            return response()->json([
                'message'  => 'Documento subido exitosamente',
                'document' => $document->load(['documentType', 'uploadedBy']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al subir el documento',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar documento
     */
    public function download($providerId, $documentId)
    {
        try {
            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $providerId)
                ->firstOrFail();

            if (!Storage::disk('documents')->exists($document->file_path)) {
                return response()->json([
                    'message'   => 'El archivo no existe en el servidor',
                    'file_path' => $document->file_path,
                ], 404);
            }

            $fileContents = Storage::disk('documents')->get($document->file_path);
            $downloadName = $document->original_filename ?? basename($document->file_path);
            $extension    = $document->file_extension ?? pathinfo($document->file_path, PATHINFO_EXTENSION);

            $mimeTypes = [
                'pdf'  => 'application/pdf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls'  => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];

            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

            return response($fileContents)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
                ->header('Content-Length', strlen($fileContents))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message'     => 'Documento no encontrado',
                'provider_id' => $providerId,
                'document_id' => $documentId,
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al descargar el documento',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver documento en el navegador (vista previa)
     */
    public function view($providerId, $documentId, Request $request)
    {
        try {
            if ($token = $request->query('token')) {
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($accessToken) {
                    auth()->setUser($accessToken->tokenable);
                }
            }

            if (!auth()->check()) {
                return response()->json(['message' => 'No autorizado. Token inválido o expirado.'], 401);
            }

            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $providerId)
                ->firstOrFail();

            if (!Storage::disk('documents')->exists($document->file_path)) {
                return response()->json(['message' => 'El archivo no existe en el servidor'], 404);
            }

            $fileContents = Storage::disk('documents')->get($document->file_path);
            $extension    = $document->file_extension ?? pathinfo($document->file_path, PATHINFO_EXTENSION);

            $previewableTypes = [
                'pdf'  => 'application/pdf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
            ];

            if (!isset($previewableTypes[$extension])) {
                return response()->json([
                    'message'   => 'Este tipo de archivo no se puede previsualizar. Use la opción de descarga.',
                    'extension' => $extension,
                ], 400);
            }

            return response($fileContents)
                ->header('Content-Type', $previewableTypes[$extension])
                ->header('Content-Disposition', 'inline; filename="' . $document->original_filename . '"')
                ->header('Content-Length', strlen($fileContents))
                ->header('Cache-Control', 'public, max-age=3600');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al visualizar el documento', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar documento
     */
    public function destroy(Provider $provider, ProviderDocument $document): JsonResponse
    {
        if ($document->provider_id !== $provider->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        try {
            if (Storage::disk('documents')->exists($document->file_path)) {
                Storage::disk('documents')->delete($document->file_path);
            }

            $document->delete();

            return response()->json(['message' => 'Documento eliminado exitosamente']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el documento',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}