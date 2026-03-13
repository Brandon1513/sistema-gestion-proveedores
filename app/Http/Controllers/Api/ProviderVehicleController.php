<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderVehicleController extends Controller
{
    /**
     * Listar vehículos del proveedor
     */
    public function index(Provider $provider): JsonResponse
    {
        $vehicles = $provider->vehicles()->get();

        return response()->json([
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Crear vehículo
     */
    public function store(Request $request, Provider $provider): JsonResponse
    {
        $validated = $request->validate([
            'plates' => 'required|string|max:20',
            'brand_model' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicle = $provider->vehicles()->create($validated);

        return response()->json([
            'message' => 'Vehículo creado exitosamente',
            'vehicle' => $vehicle,
        ], 201);
    }

    /**
     * Actualizar vehículo
     */
    public function update(Request $request, Provider $provider, ProviderVehicle $vehicle): JsonResponse
    {
        if ($vehicle->provider_id !== $provider->id) {
            return response()->json([
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'plates' => 'required|string|max:20',
            'brand_model' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicle->update($validated);

        return response()->json([
            'message' => 'Vehículo actualizado exitosamente',
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * Eliminar vehículo
     */
    public function destroy(Provider $provider, ProviderVehicle $vehicle): JsonResponse
    {
        if ($vehicle->provider_id !== $provider->id) {
            return response()->json([
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehículo eliminado exitosamente',
        ]);
    }
}