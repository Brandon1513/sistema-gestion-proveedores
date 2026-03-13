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
        $user = $request->user();
        
        // Obtener el proveedor asociado al usuario
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        // Obtener documentos del proveedor
        $documents = ProviderDocument::where('provider_id', $provider->id)->get();
        
        // Calcular estadísticas
        $stats = [
            'total' => $documents->count(),
            'approved' => $documents->where('status', 'approved')->count(),
            'pending' => $documents->where('status', 'pending')->count(),
            'rejected' => $documents->where('status', 'rejected')->count(),
            'expiring' => $documents->where('status', 'approved')
                ->filter(function ($doc) {
                    if (!$doc->expiry_date) return false;
                    $daysUntilExpiry = Carbon::parse($doc->expiry_date)->diffInDays(Carbon::today());
                    return $daysUntilExpiry <= 30;
                })
                ->count(),
        ];

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Obtener documentos del proveedor autenticado
     */
    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Obtener el proveedor asociado al usuario
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        // Obtener documentos con relaciones
        $documents = ProviderDocument::with('documentType')
            ->where('provider_id', $provider->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($document) {
                // Calcular días hasta vencimiento
                $daysUntilExpiry = null;
                if ($document->expiry_date) {
                    $daysUntilExpiry = Carbon::parse($document->expiry_date)->diffInDays(Carbon::today(), false);
                }

                return [
                    'id' => $document->id,
                    'document_type' => $document->documentType,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'status' => $document->status,
                    'issue_date' => $document->issue_date,
                    'expiry_date' => $document->expiry_date,
                    'days_until_expiry' => $daysUntilExpiry,
                    'uploaded_at' => $document->created_at,
                ];
            });

        return response()->json([
            'documents' => $documents,
        ]);
    }

    /**
     * Obtener documentos requeridos según el tipo de proveedor
     */
    public function requiredDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Obtener el proveedor asociado al usuario
        $provider = Provider::with('providerType')->where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        // Obtener documentos requeridos para este tipo de proveedor
        $requiredDocuments = DocumentType::whereHas('providerTypes', function ($query) use ($provider) {
            $query->where('provider_type_id', $provider->provider_type_id);
        })->get();

        // Obtener documentos ya cargados por el proveedor
        $uploadedDocuments = ProviderDocument::with('documentType')
            ->where('provider_id', $provider->id)
            ->get()
            ->keyBy('document_type_id');

        // Mapear documentos requeridos con su estado
        $documentsWithStatus = $requiredDocuments->map(function ($docType) use ($uploadedDocuments) {
            $uploaded = $uploadedDocuments->get($docType->id);
            
            return [
                'id' => $docType->id,
                'name' => $docType->name,
                'description' => $docType->description,
                'is_required' => $docType->is_required,
                'requires_expiry' => $docType->requires_expiry,
                'uploaded' => $uploaded ? true : false,
                'uploaded_document' => $uploaded ? [
                    'id' => $uploaded->id,
                    'status' => $uploaded->status,
                    'original_filename' => $uploaded->original_filename,
                    'file_name' => $uploaded->file_name,
                    'file_path' => $uploaded->file_path,
                    'file_extension' => $uploaded->file_extension,
                    'file_size_kb' => $uploaded->file_size_kb,
                    'issue_date' => $uploaded->issue_date,
                    'expiry_date' => $uploaded->expiry_date,
                    'uploaded_at' => $uploaded->created_at,
                    'document_type' => $uploaded->documentType ? $uploaded->documentType->name : null,
                ] : null,
            ];
        });

        return response()->json([
            'provider_type' => $provider->providerType,
            'required_documents' => $documentsWithStatus,
        ]);
    }

    /**
     * Obtener documentos próximos a vencer
     */
    public function expiringDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $today = Carbon::today();
        
        // Documentos que vencen en los próximos 30 días
        $expiringDocuments = ProviderDocument::with('documentType')
            ->where('provider_id', $provider->id)
            ->where('status', 'approved')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $today)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(30))
            ->get()
            ->map(function ($document) use ($today) {
                $expiryDate = Carbon::parse($document->expiry_date);
                $daysLeft = $today->diffInDays($expiryDate);
                
                return [
                    'id' => $document->id,
                    'name' => $document->documentType->name,
                    'file_name' => $document->file_name,
                    'expiry_date' => $document->expiry_date,
                    'days_left' => $daysLeft,
                    'urgency' => $daysLeft <= 7 ? 'critical' : ($daysLeft <= 15 ? 'warning' : 'notice'),
                ];
            });

        return response()->json([
            'expiring_documents' => $expiringDocuments,
        ]);
    }
}