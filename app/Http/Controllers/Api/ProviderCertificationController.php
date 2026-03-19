<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderCertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderCertificationController extends Controller
{
    /**
     * Listar certificaciones del proveedor
     */
    public function index(Provider $provider): JsonResponse
    {
        $certifications = $provider->certifications()->get();

        return response()->json([
            'certifications' => $certifications,
        ]);
    }

    /**
     * Crear certificación
     */
    public function store(Request $request, Provider $provider): JsonResponse
{
    $validated = $request->validate([
        'certification_type' => 'required|string|max:255',
        'other_name' => 'nullable|string|max:255',
        'certification_number' => 'nullable|string|max:255',
        'certifying_body' => 'nullable|string|max:255',
        'issue_date' => 'nullable|date',
        'expiry_date' => 'nullable|date|after_or_equal:issue_date',
    ], [
        'expiry_date.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la fecha de emisión.',
    ]);

    try {
        $certification = $provider->certifications()->create($validated);

        return response()->json([
            'message' => 'Certificación creada exitosamente',
            'certification' => $certification,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al crear certificación',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    /**
     * Actualizar certificación
     */
    public function update(Request $request, Provider $provider, ProviderCertification $certification): JsonResponse
    {
        if ($certification->provider_id !== $provider->id) {
            return response()->json([
                'message' => 'Certificación no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'certification_type' => 'required|string|max:255',
            'other_name' => 'nullable|string|max:255',
            'certification_number' => 'nullable|string|max:255',
            'certifying_body' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:issue_date',
        ], [
            'expiry_date.after' => 'La fecha de vencimiento debe ser posterior a la fecha de emisión.',
        ]);

        $certification->update($validated);

        return response()->json([
            'message' => 'Certificación actualizada exitosamente',
            'certification' => $certification,
        ]);
    }

    /**
     * Eliminar certificación
     */
    public function destroy(Provider $provider, ProviderCertification $certification): JsonResponse
    {
        if ($certification->provider_id !== $provider->id) {
            return response()->json([
                'message' => 'Certificación no encontrada',
            ], 404);
        }

        $certification->delete();

        return response()->json([
            'message' => 'Certificación eliminada exitosamente',
        ]);
    }
    // Agregar estos métodos a tu ProviderCertificationController existente

/**
 * ==========================================
 * MÉTODOS PARA PORTAL DEL PROVEEDOR
 * ==========================================
 */

/**
 * Obtener certificaciones del proveedor autenticado
 */
public function myIndex(Request $request): JsonResponse
{
    $user = $request->user();
    $provider = Provider::where('email', $user->email)->first();
    
    if (!$provider) {
        return response()->json(['message' => 'Proveedor no encontrado'], 404);
    }

    $certifications = $provider->certifications()->orderBy('created_at', 'desc')->get();

    return response()->json([
        'certifications' => $certifications,
    ]);
}

/**
 * Crear certificación del proveedor autenticado
 */
public function myStore(Request $request): JsonResponse
{
    $user = $request->user();
    $provider = Provider::where('email', $user->email)->first();
    
    if (!$provider) {
        return response()->json(['message' => 'Proveedor no encontrado'], 404);
    }

    $validated = $request->validate([
        'certification_type' => 'required|string|max:255',
        'other_name' => 'nullable|string|max:255',
        'certification_number' => 'nullable|string|max:255',
        'certifying_body' => 'nullable|string|max:255',
        'issue_date' => 'required|date',
        'expiry_date' => 'required|date|after_or_equal:issue_date',
    ], [
        'expiry_date.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la fecha de emisión.',
    ]);

    try {
        $certification = $provider->certifications()->create($validated);
        
        return response()->json([
            'message' => 'Certificación creada exitosamente',
            'certification' => $certification,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al crear certificación',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Actualizar certificación del proveedor autenticado
 */
public function myUpdate(Request $request, $certificationId): JsonResponse
{
    $user = $request->user();
    $provider = Provider::where('email', $user->email)->first();
    
    if (!$provider) {
        return response()->json(['message' => 'Proveedor no encontrado'], 404);
    }

    $certification = ProviderCertification::where('id', $certificationId)
        ->where('provider_id', $provider->id)
        ->firstOrFail();

    $validated = $request->validate([
        'certification_type' => 'required|string|max:255',
        'other_name' => 'nullable|string|max:255',
        'certification_number' => 'nullable|string|max:255',
        'certifying_body' => 'nullable|string|max:255',
        'issue_date' => 'required|date',
        'expiry_date' => 'required|date|after_or_equal:issue_date',
    ], [
        'expiry_date.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la fecha de emisión.',
    ]);

    $certification->update($validated);

    return response()->json([
        'message' => 'Certificación actualizada exitosamente',
        'certification' => $certification,
    ]);
}

/**
 * Eliminar certificación del proveedor autenticado
 */
public function myDestroy(Request $request, $certificationId): JsonResponse
{
    $user = $request->user();
    $provider = Provider::where('email', $user->email)->first();
    
    if (!$provider) {
        return response()->json(['message' => 'Proveedor no encontrado'], 404);
    }

    $certification = ProviderCertification::where('id', $certificationId)
        ->where('provider_id', $provider->id)
        ->firstOrFail();

    $certification->delete();

    return response()->json([
        'message' => 'Certificación eliminada exitosamente',
    ]);
}

    /**
     * Listar TODAS las certificaciones de todos los proveedores
     * GET /api/certifications
     */
    public function globalIndex(Request $request): JsonResponse
    {
        $query = \App\Models\ProviderCertification::with([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
        ]);
 
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('certification_type', 'like', "%{$request->search}%")
                  ->orWhere('other_name', 'like', "%{$request->search}%")
                  ->orWhere('certifying_body', 'like', "%{$request->search}%")
                  ->orWhereHas('provider', fn($p) =>
                      $p->where('business_name', 'like', "%{$request->search}%")
                        ->orWhere('rfc', 'like', "%{$request->search}%")
                  );
            });
        }
 
        if ($request->provider_type_id) {
            $query->whereHas('provider', fn($p) =>
                $p->where('provider_type_id', $request->provider_type_id)
            );
        }
 
        $certifications = $query->orderBy('expiry_date')->get();
 
        return response()->json([
            'certifications' => $certifications,
            'total'          => $certifications->count(),
        ]);
    }
}