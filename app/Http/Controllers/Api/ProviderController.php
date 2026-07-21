<?php

namespace App\Http\Controllers\Api;

use App\Mail\ProviderInvitation as ProviderInvitationMail;
use App\Models\ProviderInvitation;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProviderRequest;
use App\Http\Requests\UpdateProviderRequest;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderController extends Controller
{
    /**
     * Lista de proveedores
     */
    public function index(Request $request): JsonResponse
    {
        $query = Provider::with(['providerType', 'contacts', 'documents.documentType']);

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('provider_type_id')) {
            $query->where('provider_type_id', $request->provider_type_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('rfc', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $providers = $query->paginate($perPage);

        return response()->json($providers);
    }

    /**
     * Crear proveedor
     */
    public function store(StoreProviderRequest $request): JsonResponse
{
    try {
        DB::beginTransaction();

        $provider = Provider::create(array_merge(
            $request->validated(),
            ['created_by' => auth()->id()]
        ));

        // Crear contactos
        if ($request->has('contacts')) {
            foreach ($request->contacts as $contact) {
                $provider->contacts()->create($contact);
            }
        }

        // Crear vehículos
        if ($request->has('vehicles')) {
            foreach ($request->vehicles as $vehicle) {
                $provider->vehicles()->create($vehicle);
            }
        }

        // Crear personal
        if ($request->has('personnel')) {
            foreach ($request->personnel as $person) {
                $provider->personnel()->create($person);
            }
        }

        // Crear certificaciones
        if ($request->has('certifications')) {
            foreach ($request->certifications as $certification) {
                $provider->certifications()->create($certification);
            }
        }

        DB::commit();

        // ✅ Enviar invitación automática para que el proveedor cree su contraseña
        if ($provider->email) {
            try {
                // Cancelar invitaciones previas pendientes para este email
                ProviderInvitation::where('email', $provider->email)
                    ->where('status', 'pending')
                    ->update(['status' => 'expired']);

                $invitation = ProviderInvitation::create([
                    'email'            => $provider->email,
                    'token'            => ProviderInvitation::generateToken(),
                    'provider_type_id' => $provider->provider_type_id,
                    'invited_by'       => auth()->id(),
                    'status'           => 'pending',
                    'expires_at'       => Carbon::now()->addDays(7),
                ]);

                Mail::to($invitation->email)->send(new ProviderInvitationMail($invitation));

            } catch (\Exception $mailError) {
                \Log::warning('Proveedor creado pero no se pudo enviar invitación: ' . $mailError->getMessage());
            }
        }

        return response()->json([
            'message' => 'Proveedor creado exitosamente. Se envió una invitación al correo del proveedor para configurar su acceso.',
            'provider' => $provider->load(['providerType', 'contacts', 'vehicles', 'personnel', 'certifications']),
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al crear proveedor',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Mostrar proveedor
     */
  public function show(Provider $provider): JsonResponse
{
    $provider->load([
        'providerType',
        'contacts',
        'vehicles',
        'personnel',
        'certifications',
    ]);

    return response()->json([
        'provider' => $provider,
    ]);
}

    /**
     * Actualizar proveedor
     */
    public function update(UpdateProviderRequest $request, Provider $provider): JsonResponse
    {
        try {
            DB::beginTransaction();

            $provider->update($request->validated());

            // Actualizar contactos
            if ($request->has('contacts')) {
                $provider->contacts()->delete();
                foreach ($request->contacts as $contact) {
                    $provider->contacts()->create($contact);
                }
            }

            // Actualizar vehículos
            if ($request->has('vehicles')) {
                $provider->vehicles()->delete();
                foreach ($request->vehicles as $vehicle) {
                    $provider->vehicles()->create($vehicle);
                }
            }

            // Actualizar personal
            if ($request->has('personnel')) {
                $provider->personnel()->delete();
                foreach ($request->personnel as $person) {
                    $provider->personnel()->create($person);
                }
            }

            // Actualizar certificaciones
            if ($request->has('certifications')) {
                $provider->certifications()->delete();
                foreach ($request->certifications as $certification) {
                    $provider->certifications()->create($certification);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Proveedor actualizado exitosamente',
                'provider' => $provider->load(['providerType', 'contacts', 'vehicles', 'personnel', 'certifications']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar proveedor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar proveedor (soft delete)
     */
    public function destroy(Provider $provider): JsonResponse
    {
        $provider->delete();

        return response()->json([
            'message' => 'Proveedor eliminado exitosamente',
        ]);
    }

    /**
     * Cambiar estado del proveedor
     */
    public function updateStatus(Request $request, Provider $provider): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,active,inactive,rejected',
            'observations' => 'nullable|string',
        ]);

        $provider->update([
            'status' => $request->status,
            'observations' => $request->observations,
        ]);

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'provider' => $provider,
        ]);
    }
}