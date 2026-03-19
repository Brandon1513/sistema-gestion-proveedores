<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ProviderAccountController extends Controller
{
    /**
     * Listar cuentas de proveedores con su información vinculada
     * GET /api/provider-accounts
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->whereHas('roles', fn($q) => $q->where('name', 'proveedor'));

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name',  'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', (bool) $request->is_active);
        }

        $users = $query->latest()->paginate(20);

        // Enriquecer cada usuario con datos del proveedor vinculado
        $users->getCollection()->transform(function ($user) {
            $provider = Provider::where('email', $user->email)
                ->with('providerType:id,name')
                ->first();

            $user->provider         = $provider ? [
                'id'            => $provider->id,
                'business_name' => $provider->business_name,
                'rfc'           => $provider->rfc,
                'status'        => $provider->status,
                'provider_type' => $provider->providerType,
            ] : null;

            return $user;
        });

        return response()->json($users);
    }

    /**
     * Activar / desactivar cuenta de proveedor — cambia providers.status
     * PATCH /api/provider-accounts/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::whereHas('roles', fn($q) => $q->where('name', 'proveedor'))
            ->findOrFail($id);

        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json([
                'message' => 'No se encontró el proveedor vinculado a esta cuenta',
            ], 404);
        }

        // Toggle entre active e inactive
        $newStatus = $provider->status === 'active' ? 'inactive' : 'active';
        $provider->update(['status' => $newStatus]);

        return response()->json([
            'message'    => $newStatus === 'active'
                ? 'Cuenta del proveedor activada correctamente'
                : 'Cuenta del proveedor desactivada correctamente',
            'status'     => $newStatus,
            'is_active'  => $newStatus === 'active',
        ]);
    }

    /**
     * Resetear contraseña manualmente (sin email)
     * PATCH /api/provider-accounts/{id}/reset-password
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::whereHas('roles', fn($q) => $q->where('name', 'proveedor'))
            ->findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required'  => 'La contraseña es requerida',
            'password.min'       => 'Mínimo 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        // Revocar todos los tokens activos
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña restablecida correctamente',
        ]);
    }

    /**
     * Enviar email de reset al proveedor
     * POST /api/provider-accounts/{id}/send-reset
     */
    public function sendReset(int $id): JsonResponse
    {
        $user = User::whereHas('roles', fn($q) => $q->where('name', 'proveedor'))
            ->findOrFail($id);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => "Correo de restablecimiento enviado a {$user->email}",
            ]);
        }

        return response()->json([
            'message' => 'No se pudo enviar el correo. Intenta de nuevo.',
        ], 500);
    }
}