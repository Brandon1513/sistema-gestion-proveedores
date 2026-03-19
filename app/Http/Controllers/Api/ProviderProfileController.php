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
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json(['provider' => $provider]);
    }

    /**
     * Actualizar información general del proveedor
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Empresa
            'business_name'        => 'required|string|max:255',
            'rfc'                  => 'required|string|max:13',
            'trade_name'           => 'nullable|string|max:255',
            'legal_representative' => 'nullable|string|max:255',
            // Contacto
            'phone'                => 'nullable|string|max:20',
            // Dirección
            'street'               => 'nullable|string|max:255',
            'exterior_number'      => 'nullable|string|max:10',
            'interior_number'      => 'nullable|string|max:10',
            'neighborhood'         => 'nullable|string|max:100',
            'city'                 => 'nullable|string|max:100',
            'state'                => 'nullable|string|max:100',
            'postal_code'          => 'nullable|string|max:10',
            'country'              => 'nullable|string|max:100',
            // Bancaria
            'bank'                 => 'nullable|string|max:255',
            'bank_branch'          => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:255',
            'clabe'                => 'nullable|string|max:18',
            // Crédito
            'credit_amount'        => 'nullable|numeric|min:0',
            'credit_days'          => 'nullable|integer|min:0',
            // Productos y servicios
            'products'             => 'nullable|string',
            'services'             => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        // ✅ Usar validated() en lugar de all() para mayor seguridad
        $provider->update($validator->validated());

        return response()->json([
            'message'  => 'Información actualizada exitosamente',
            'provider' => $provider->fresh(),
        ]);
    }

    /**
     * Obtener contactos del proveedor
     */
    public function contacts(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json([
            'contacts' => ProviderContact::where('provider_id', $provider->id)->get()
        ]);
    }

    /**
     * Crear o actualizar contacto
     */
    public function storeContact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'           => 'nullable|exists:provider_contacts,id',
            'contact_type' => 'required|in:sales,quality,billing',
            'name'         => 'required|string|max:255',
            'position'     => 'nullable|string|max:100',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:20',
            'extension'    => 'nullable|string|max:10',
            'is_primary'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Errores de validación', 'errors' => $validator->errors()], 422);
        }

        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $data = [
            'type'       => $request->contact_type,
            'name'       => $request->name,
            'position'   => $request->position,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'extension'  => $request->extension,
            'is_primary' => $request->is_primary ?? false,
        ];

        if ($request->id) {
            $contact = ProviderContact::where('id', $request->id)->where('provider_id', $provider->id)->firstOrFail();
            $contact->update($data);
            $message = 'Contacto actualizado exitosamente';
        } else {
            $contact = ProviderContact::create(array_merge($data, ['provider_id' => $provider->id]));
            $message = 'Contacto creado exitosamente';
        }

        return response()->json(['message' => $message, 'contact' => $contact]);
    }

    /**
     * Eliminar contacto
     */
    public function deleteContact(Request $request, $contactId): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        ProviderContact::where('id', $contactId)->where('provider_id', $provider->id)->firstOrFail()->delete();

        return response()->json(['message' => 'Contacto eliminado exitosamente']);
    }

    /**
     * Obtener vehículos del proveedor
     */
    public function vehicles(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        return response()->json(['vehicles' => ProviderVehicle::where('provider_id', $provider->id)->get()]);
    }

    /**
     * Crear o actualizar vehículo
     */
    public function storeVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'          => 'nullable|exists:provider_vehicles,id',
            'brand_model' => 'required|string|max:255',
            'color'       => 'nullable|string|max:50',
            'plates'      => 'required|string|max:20',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Errores de validación', 'errors' => $validator->errors()], 422);
        }

        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $data = [
            'brand_model' => $request->brand_model,
            'color'       => $request->color,
            'plates'      => $request->plates,
            'is_active'   => $request->is_active ?? true,
        ];

        if ($request->id) {
            $vehicle = ProviderVehicle::where('id', $request->id)->where('provider_id', $provider->id)->firstOrFail();
            $vehicle->update($data);
            $message = 'Vehículo actualizado exitosamente';
        } else {
            $vehicle = ProviderVehicle::create(array_merge($data, ['provider_id' => $provider->id]));
            $message = 'Vehículo agregado exitosamente';
        }

        return response()->json(['message' => $message, 'vehicle' => $vehicle]);
    }

    /**
     * Eliminar vehículo
     */
    public function deleteVehicle(Request $request, $vehicleId): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        ProviderVehicle::where('id', $vehicleId)->where('provider_id', $provider->id)->firstOrFail()->delete();

        return response()->json(['message' => 'Vehículo eliminado exitosamente']);
    }

    /**
     * Obtener personal del proveedor
     */
    public function personnel(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        return response()->json(['personnel' => ProviderPersonnel::where('provider_id', $provider->id)->get()]);
    }

    /**
     * Crear o actualizar personal
     */
    public function storePersonnel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'                    => 'nullable|exists:provider_personnel,id',
            'full_name'             => 'required|string|max:255',
            'position'              => 'nullable|string|max:255',
            'identification_number' => 'nullable|string|max:255',
            'is_active'             => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Errores de validación', 'errors' => $validator->errors()], 422);
        }

        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $data = [
            'full_name'             => $request->full_name,
            'position'              => $request->position,
            'identification_number' => $request->identification_number,
            'is_active'             => $request->is_active ?? true,
        ];

        if ($request->id) {
            $personnel = ProviderPersonnel::where('id', $request->id)->where('provider_id', $provider->id)->firstOrFail();
            $personnel->update($data);
            $message = 'Personal actualizado exitosamente';
        } else {
            $personnel = ProviderPersonnel::create(array_merge($data, ['provider_id' => $provider->id]));
            $message   = 'Personal agregado exitosamente';
        }

        return response()->json(['message' => $message, 'personnel' => $personnel]);
    }

    /**
     * Eliminar personal
     */
    public function deletePersonnel(Request $request, $personnelId): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        ProviderPersonnel::where('id', $personnelId)->where('provider_id', $provider->id)->firstOrFail()->delete();

        return response()->json(['message' => 'Personal eliminado exitosamente']);
    }
}