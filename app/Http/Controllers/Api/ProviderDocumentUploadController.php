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
use Carbon\Carbon;

class ProviderDocumentUploadController extends Controller
{
    /**
     * Subir documento del proveedor
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file'             => 'required|file|max:10240',
            'issue_date'       => 'nullable|date',
            'expiry_date'      => 'nullable|date|after:issue_date',
            'product_name'     => 'nullable|string|max:255',
        ]);

        try {
            $user = $request->user();

            $provider = Provider::where('email', $user->email)->first();
            if (!$provider) {
                return response()->json(['message' => 'Proveedor no encontrado'], 404);
            }

            $documentType = DocumentType::findOrFail($request->document_type_id);

            // ✅ Calcular expiry_date automáticamente si el tipo tiene expiry_months
            $expiryDate = $request->expiry_date ?? null;
            if ($documentType->expiry_months && $request->filled('issue_date')) {
                $expiryDate = Carbon::parse($request->issue_date)
                    ->addMonths($documentType->expiry_months)
                    ->format('Y-m-d');
            }

            // Procesar archivo
            $file         = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension    = $file->getClientOriginalExtension();
            $fileName     = Str::slug($provider->business_name) . '_' .
                            Str::slug($documentType->name) . '_' .
                            time() . '.' . $extension;

            $path = $file->storeAs(
                'providers/' . $provider->id . '/documents',
                $fileName,
                'documents'
            );

            // ✅ Si el tipo permite múltiples archivos, siempre crear uno nuevo
            // Si no permite múltiples, buscar el existente para reemplazarlo
            $existingDocument = null;
            if (!$documentType->allows_multiple) {
                $existingDocument = ProviderDocument::where('provider_id', $provider->id)
                    ->where('document_type_id', $request->document_type_id)
                    ->first();
            }

            if ($existingDocument) {
                // Reemplazar documento único existente
                if ($existingDocument->file_path) {
                    Storage::disk('documents')->delete($existingDocument->file_path);
                }
                $existingDocument->update([
                    'original_filename' => $originalName,
                    'file_path'         => $path,
                    'file_extension'    => $extension,
                    'file_size_kb'      => (int) round($file->getSize() / 1024),
                    'issue_date'        => $request->issue_date,
                    'expiry_date'       => $expiryDate,
                    'product_name'      => $request->product_name ?? null,
                    'status'            => 'pending',
                ]);
                $document = $existingDocument;
            } else {
                // Crear nuevo documento (siempre para allows_multiple, o cuando no existe)
                $document = ProviderDocument::create([
                    'provider_id'       => $provider->id,
                    'document_type_id'  => $request->document_type_id,
                    'original_filename' => $originalName,
                    'file_path'         => $path,
                    'file_extension'    => $extension,
                    'file_size_kb'      => (int) round($file->getSize() / 1024),
                    'issue_date'        => $request->issue_date,
                    'expiry_date'       => $expiryDate,
                    'product_name'      => $request->product_name ?? null,
                    'status'            => 'pending',
                ]);
            }

            $document->load('documentType');

            // Notificar a Calidad
            try {
                $recipients = User::role('calidad')->pluck('email')->toArray();
                if (!empty($recipients)) {
                    foreach ($recipients as $email) {
                        Mail::to($email)->send(new NewDocumentUploadedMail($document));
                    }
                    \Log::info('Email de nuevo documento enviado a: ' . implode(', ', $recipients));
                } else {
                    \Log::warning('No hay usuarios de Calidad para notificar');
                }
            } catch (\Exception $e) {
                \Log::error('Error al enviar email de nuevo documento: ' . $e->getMessage());
            }

            return response()->json([
                'message'  => 'Documento cargado exitosamente',
                'document' => $document,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error al subir documento: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al subir documento',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar documento del proveedor
     */
    public function delete(Request $request, $documentId): JsonResponse
    {
        try {
            $user     = $request->user();
            $provider = Provider::where('email', $user->email)->first();
            if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $provider->id)
                ->firstOrFail();

            if ($document->status === 'approved') {
                return response()->json(['message' => 'No se puede eliminar un documento aprobado'], 400);
            }

            if ($document->file_path) {
                Storage::disk('documents')->delete($document->file_path);
            }
            $document->delete();

            return response()->json(['message' => 'Documento eliminado exitosamente']);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar documento', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Descargar documento
     */
    public function download(Request $request, $documentId): mixed
    {
        try {
            $user     = $request->user();
            $provider = Provider::where('email', $user->email)->first();
            if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

            $document = ProviderDocument::where('id', $documentId)
                ->where('provider_id', $provider->id)
                ->firstOrFail();

            if (!Storage::disk('documents')->exists($document->file_path)) {
                return response()->json(['message' => 'Archivo no encontrado'], 404);
            }

            return Storage::disk('documents')->download($document->file_path, $document->original_filename);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al descargar documento', 'error' => $e->getMessage()], 500);
        }
    }
}