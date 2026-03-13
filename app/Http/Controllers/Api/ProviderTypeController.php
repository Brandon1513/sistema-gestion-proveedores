<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderType;
use Illuminate\Http\JsonResponse;

class ProviderTypeController extends Controller
{
    /**
     * Lista de tipos de proveedores
     */
    public function index(): JsonResponse
    {
        $providerTypes = ProviderType::where('is_active', true)
            ->withCount('providers')
            ->get();

        return response()->json([
            'provider_types' => $providerTypes,
        ]);
    }

    /**
     * Mostrar tipo de proveedor con sus documentos requeridos
     */
    public function show(ProviderType $providerType): JsonResponse
    {
        $providerType->load(['documentTypes' => function ($query) {
            $query->wherePivot('is_required', true);
        }]);

        return response()->json([
            'provider_type' => $providerType,
        ]);
    }

    /**
     * Documentos requeridos por tipo de proveedor
     */
    public function requiredDocuments(ProviderType $providerType): JsonResponse
    {
        $documents = $providerType->documentTypes()
            ->wherePivot('is_required', true)
            ->get()
            ->groupBy('category');

        return response()->json([
            'required_documents' => $documents,
            'total' => $providerType->documentTypes()->wherePivot('is_required', true)->count(),
        ]);
    }
}