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
     * Documentos requeridos para un proveedor
     */
    public function required(Provider $provider): JsonResponse
    {
        $requiredDocuments = $provider->providerType
            ->documentTypes()
            ->wherePivot('is_required', true)
            ->get();

        $uploadedDocuments = $provider->documents()
            ->whereIn('document_type_id', $requiredDocuments->pluck('id'))
            ->where('status', 'approved')
            ->latest('version')
            ->get()
            ->groupBy('document_type_id')
            ->map->first(); // Obtener solo la última versión de cada tipo

        $documents = $requiredDocuments->map(function ($docType) use ($uploadedDocuments) {
            return [
                'document_type' => $docType,
                'uploaded' => isset($uploadedDocuments[$docType->id]),
                'document' => $uploadedDocuments[$docType->id] ?? null,
                'is_required' => true,
            ];
        });

        $totalRequired = $requiredDocuments->count();
        $totalUploaded = $uploadedDocuments->count();

        return response()->json([
            'required_documents' => $documents,
            'total_required' => $totalRequired,
            'total_uploaded' => $totalUploaded,
            'completion_percentage' => $totalRequired > 0 
                ? round(($totalUploaded / $totalRequired) * 100, 2) 
                : 0,
        ]);
    }

    /**
     * Subir documento
     */
    public function store(Request $request, Provider $provider): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
        ], [
            'file.max' => 'El archivo no debe superar los 10MB',
            'file.mimes' => 'Solo se permiten archivos PDF, imágenes, Word y Excel',
            'expiry_date.after' => 'La fecha de vencimiento debe ser posterior a hoy',
        ]);

        try {
            $file = $request->file('file');
            $documentType = DocumentType::findOrFail($validated['document_type_id']);
            
            // Generar nombre único para el archivo
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Guardar archivo en storage/app/documents
            $filePath = $file->storeAs(
                'providers/' . $provider->id . '/documents',
                $fileName,
                'documents'
            );

            // Incrementar versión si ya existe un documento del mismo tipo
            $lastVersion = ProviderDocument::where('provider_id', $provider->id)
                ->where('document_type_id', $validated['document_type_id'])
                ->max('version') ?? 0;

            // Crear registro del documento
            $document = $provider->documents()->create([
                'document_type_id' => $validated['document_type_id'],
                'file_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size_kb' => round($file->getSize() / 1024),
                'issue_date' => $validated['issue_date'] ?? null,
                'expiry_date' => $validated['expiry_date'] ?? null,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'version' => $lastVersion + 1,
                'uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Documento subido exitosamente',
                'document' => $document->load(['documentType', 'uploadedBy']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al subir el documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar documento
     * 
     * ⭐ MÉTODO CORREGIDO - Ahora funciona correctamente
     */
    public function download($providerId, $documentId)
    {
        try {
            // Buscar el documento
            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $providerId)
                ->firstOrFail();

            // ✅ CORRECCIÓN: Usar Storage::disk() en lugar de Storage::path()
            // Verificar que el archivo existe
            if (!Storage::disk('documents')->exists($document->file_path)) {
                return response()->json([
                    'message' => 'El archivo no existe en el servidor',
                    'file_path' => $document->file_path,
                ], 404);
            }

            // Obtener el contenido del archivo desde el disco 'documents'
            $fileContents = Storage::disk('documents')->get($document->file_path);
            
            // Determinar el nombre del archivo para descarga
            $downloadName = $document->original_filename ?? basename($document->file_path);

            // Determinar el tipo MIME basado en la extensión
            $extension = $document->file_extension ?? 
                        pathinfo($document->file_path, PATHINFO_EXTENSION);
            
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];

            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

            // ✅ Retornar el archivo como descarga con los headers correctos
            return response($fileContents)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
                ->header('Content-Length', strlen($fileContents))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Documento no encontrado',
                'provider_id' => $providerId,
                'document_id' => $documentId,
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al descargar el documento',
                'error' => $e->getMessage(),
                'file_path' => $document->file_path ?? 'N/A',
            ], 500);
        }
    }

    /**
     * Ver documento en el navegador (opcional - para vista previa)
     * 
     * ⭐ TAMBIÉN CORREGIDO
     */
    public function view($providerId, $documentId, Request $request)
{
    try {
        // ⭐ Autenticación con token de query parameter
        if ($token = $request->query('token')) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if ($accessToken) {
                auth()->setUser($accessToken->tokenable);
            }
        }

        // Verificar autenticación
        if (!auth()->check()) {
            return response()->json([
                'message' => 'No autorizado. Token inválido o expirado.',
            ], 401);
        }

        // Buscar el documento
        $document = ProviderDocument::where('id', $documentId)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        // Verificar que el archivo existe
        if (!Storage::disk('documents')->exists($document->file_path)) {
            return response()->json([
                'message' => 'El archivo no existe en el servidor',
            ], 404);
        }

        // Obtener contenido del archivo
        $fileContents = Storage::disk('documents')->get($document->file_path);
        
        $extension = $document->file_extension ?? 
                    pathinfo($document->file_path, PATHINFO_EXTENSION);
        
        // Solo tipos previsualizables
        $previewableTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        if (!isset($previewableTypes[$extension])) {
            return response()->json([
                'message' => 'Este tipo de archivo no se puede previsualizar. Use la opción de descarga.',
                'extension' => $extension,
            ], 400);
        }

        $mimeType = $previewableTypes[$extension];

        // ⭐ DIFERENCIA CLAVE: inline en lugar de attachment
        return response($fileContents)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $document->original_filename . '"')
            ->header('Content-Length', strlen($fileContents))
            ->header('Cache-Control', 'public, max-age=3600');

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['message' => 'Documento no encontrado'], 404);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al visualizar el documento',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Eliminar documento
     */
    public function destroy(Provider $provider, ProviderDocument $document): JsonResponse
    {
        // Verificar que el documento pertenece al proveedor
        if ($document->provider_id !== $provider->id) {
            return response()->json([
                'message' => 'No autorizado',
            ], 403);
        }

        try {
            // Eliminar archivo físico
            if (Storage::disk('documents')->exists($document->file_path)) {
                Storage::disk('documents')->delete($document->file_path);
            }

            // Soft delete del registro
            $document->delete();

            return response()->json([
                'message' => 'Documento eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}