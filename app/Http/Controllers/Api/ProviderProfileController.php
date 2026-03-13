<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderContact;
use App\Models\ProviderVehicle;
use App\Models\ProviderPersonnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProviderProfileController extends Controller
{
    /**
     * Obtener perfil completo del proveedor
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $provider = Provider::with([
            'providerType',
            'contacts',
            'vehicles',
            'personnel',
            'certifications'
        ])->where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        return response()->json([
            'provider' => $provider,
        ]);
    }

    /**
     * Actualizar información general del proveedor
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'rfc' => 'required|string|max:13',
            'trade_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:10',
            'interior_number' => 'nullable|string|max:10',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $provider->update($request->all());

        return response()->json([
            'message' => 'Información actualizada exitosamente',
            'provider' => $provider->fresh(),
        ]);
    }

    /**
     * Obtener contactos del proveedor
     */
    public function contacts(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $contacts = ProviderContact::where('provider_id', $provider->id)->get();

        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Crear o actualizar contacto
     */
    public function storeContact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:provider_contacts,id',
            'contact_type' => 'required|in:sales,quality,billing',
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'extension' => 'nullable|string|max:10',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        if ($request->id) {
            // Actualizar contacto existente
            $contact = ProviderContact::where('id', $request->id)
                ->where('provider_id', $provider->id)
                ->firstOrFail();
            
            $contact->update([
                'type' => $request->contact_type,
                'name' => $request->name,
                'position' => $request->position,
                'email' => $request->email,
                'phone' => $request->phone,
                'extension' => $request->extension,
                'is_primary' => $request->is_primary ?? false,
            ]);
            $message = 'Contacto actualizado exitosamente';
        } else {
            // Crear nuevo contacto
            $contact = ProviderContact::create([
                'provider_id' => $provider->id,
                'type' => $request->contact_type,
                'name' => $request->name,
                'position' => $request->position,
                'email' => $request->email,
                'phone' => $request->phone,
                'extension' => $request->extension,
                'is_primary' => $request->is_primary ?? false,
            ]);
            $message = 'Contacto creado exitosamente';
        }

        return response()->json([
            'message' => $message,
            'contact' => $contact,
        ]);
    }

    /**
     * Eliminar contacto
     */
    public function deleteContact(Request $request, $contactId): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $contact = ProviderContact::where('id', $contactId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'message' => 'Contacto eliminado exitosamente',
        ]);
    }

    /**
     * Obtener vehículos del proveedor
     */
    public function vehicles(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $vehicles = ProviderVehicle::where('provider_id', $provider->id)->get();

        return response()->json(['vehicles' => $vehicles]);
    }

    /**
     * Crear o actualizar vehículo
     */
    public function storeVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:provider_vehicles,id',
            'brand_model' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'plates' => 'required|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        if ($request->id) {
            $vehicle = ProviderVehicle::where('id', $request->id)
                ->where('provider_id', $provider->id)
                ->firstOrFail();
            
            $vehicle->update([
                'brand_model' => $request->brand_model,
                'color' => $request->color,
                'plates' => $request->plates,
                'is_active' => $request->is_active ?? true,
            ]);
            $message = 'Vehículo actualizado exitosamente';
        } else {
            $vehicle = ProviderVehicle::create([
                'provider_id' => $provider->id,
                'brand_model' => $request->brand_model,
                'color' => $request->color,
                'plates' => $request->plates,
                'is_active' => $request->is_active ?? true,
            ]);
            $message = 'Vehículo agregado exitosamente';
        }

        return response()->json([
            'message' => $message,
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * Eliminar vehículo
     */
    public function deleteVehicle(Request $request, $vehicleId): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $vehicle = ProviderVehicle::where('id', $vehicleId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehículo eliminado exitosamente',
        ]);
    }

    /**
     * Obtener personal del proveedor
     */
    public function personnel(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $personnel = ProviderPersonnel::where('provider_id', $provider->id)->get();

        return response()->json(['personnel' => $personnel]);
    }

    /**
     * Crear o actualizar personal
     */
    public function storePersonnel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:provider_personnel,id',
            'full_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'identification_number' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        if ($request->id) {
            $personnel = ProviderPersonnel::where('id', $request->id)
                ->where('provider_id', $provider->id)
                ->firstOrFail();
            
            $personnel->update([
                'full_name' => $request->full_name,
                'position' => $request->position,
                'identification_number' => $request->identification_number,
                'is_active' => $request->is_active ?? true,
            ]);
            $message = 'Personal actualizado exitosamente';
        } else {
            $personnel = ProviderPersonnel::create([
                'provider_id' => $provider->id,
                'full_name' => $request->full_name,
                'position' => $request->position,
                'identification_number' => $request->identification_number,
                'is_active' => $request->is_active ?? true,
            ]);
            $message = 'Personal agregado exitosamente';
        }

        return response()->json([
            'message' => $message,
            'personnel' => $personnel,
        ]);
    }

    /**
     * Eliminar personal
     */
    public function deletePersonnel(Request $request, $personnelId): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        
        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $personnel = ProviderPersonnel::where('id', $personnelId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        $personnel->delete();

        return response()->json([
            'message' => 'Personal eliminado exitosamente',
        ]);
    }
}