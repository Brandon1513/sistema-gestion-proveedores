<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Mail\NewDocumentUploadedMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProviderDocumentUploadController extends Controller
{
    /**
     * Subir documento del proveedor
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|max:10240', // 10MB máximo
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:issue_date',
        ]);

        try {
            $user = $request->user();
            
            // Obtener el proveedor asociado al usuario
            $provider = Provider::where('email', $user->email)->first();
            
            if (!$provider) {
                return response()->json([
                    'message' => 'Proveedor no encontrado',
                ], 404);
            }

            // Obtener el tipo de documento
            $documentType = DocumentType::findOrFail($request->document_type_id);

            // Procesar el archivo
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            
            // Generar nombre único
            $fileName = Str::slug($provider->business_name) . '_' . 
                        Str::slug($documentType->name) . '_' . 
                        time() . '.' . $extension;

            // Guardar en storage usando el disk 'documents'
            $path = $file->storeAs(
                'providers/' . $provider->id . '/documents',
                $fileName,
                'documents'
            );

            // Verificar si ya existe un documento de este tipo
            $existingDocument = ProviderDocument::where('provider_id', $provider->id)
                ->where('document_type_id', $request->document_type_id)
                ->first();

            if ($existingDocument) {
                // Eliminar archivo anterior si existe
                if ($existingDocument->file_path) {
                    Storage::disk('documents')->delete($existingDocument->file_path);
                }

                // Actualizar documento existente
                $existingDocument->update([
                    'original_filename' => $originalName,
                    'file_path' => $path,
                    'file_extension' => $extension,
                    'file_size_kb' => (int) round($file->getSize() / 1024),
                    'issue_date' => $request->issue_date,
                    'expiry_date' => $request->expiry_date,
                    'status' => 'pending', // Volver a pendiente al renovar
                ]);

                $document = $existingDocument;
            } else {
                // Crear nuevo documento
                $document = ProviderDocument::create([
                    'provider_id' => $provider->id,
                    'document_type_id' => $request->document_type_id,
                    'original_filename' => $originalName,
                    'file_path' => $path,
                    'file_extension' => $extension,
                    'file_size_kb' => (int) round($file->getSize() / 1024),
                    'issue_date' => $request->issue_date,
                    'expiry_date' => $request->expiry_date,
                    'status' => 'pending',
                ]);
            }

            // Cargar relación con documentType
            $document->load('documentType');

            // 📧 ENVIAR NOTIFICACIÓN A CALIDAD/ADMIN
            try {
                // Obtener emails de usuarios con rol 'calidad'
                //$calidadEmails = User::role('calidad')->pluck('email')->toArray();
                
                // Agregar también admin y super_admin
                //$adminEmails = User::role(['admin', 'super_admin'])->pluck('email')->toArray();
                
                // Combinar todos los emails
                // Solo enviar a usuarios con rol 'calidad'
                $recipients = User::role('calidad')->pluck('email')->toArray();
                
                // Enviar notificación a cada uno
                if (!empty($recipients)) {
                    foreach ($recipients as $email) {
                        Mail::to($email)->send(new NewDocumentUploadedMail($document));
                    }
                    
                    \Log::info('Email de nuevo documento enviado a: ' . implode(', ', $recipients));
                } else {
                    \Log::warning('No hay usuarios de Calidad/Admin para notificar');
                }
            } catch (\Exception $e) {
                \Log::error('Error al enviar email de nuevo documento: ' . $e->getMessage());
                // No lanzar excepción para no interrumpir el flujo
            }

            return response()->json([
                'message' => 'Documento cargado exitosamente',
                'document' => $document,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error al subir documento: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al subir documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar documento del proveedor
     */
    public function delete(Request $request, $documentId): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = Provider::where('email', $user->email)->first();
            
            if (!$provider) {
                return response()->json([
                    'message' => 'Proveedor no encontrado',
                ], 404);
            }

            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $provider->id)
                ->firstOrFail();

            // Solo permitir eliminar si está pendiente o rechazado
            if ($document->status === 'approved') {
                return response()->json([
                    'message' => 'No se puede eliminar un documento aprobado',
                ], 400);
            }

            // Eliminar archivo del storage
            if ($document->file_path) {
                Storage::disk('documents')->delete($document->file_path);
            }

            // Eliminar registro
            $document->delete();

            return response()->json([
                'message' => 'Documento eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar documento
     */
    public function download(Request $request, $documentId): mixed
    {
        try {
            $user = $request->user();
            $provider = Provider::where('email', $user->email)->first();
            
            if (!$provider) {
                return response()->json([
                    'message' => 'Proveedor no encontrado',
                ], 404);
            }

            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $provider->id)
                ->firstOrFail();

            if (!Storage::disk('documents')->exists($document->file_path)) {
                return response()->json([
                    'message' => 'Archivo no encontrado',
                ], 404);
            }

            return Storage::disk('documents')->download(
                $document->file_path,
                $document->original_filename
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al descargar documento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}