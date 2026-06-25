<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Provider;
use App\Models\ProviderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ProviderAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->whereHas('roles', fn($q) => $q->where('name', 'proveedor'));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', (bool) $request->is_active);
        }

        // ── Filtro por tipo de proveedor ──────────────────────────────────
        if ($request->filled('provider_type_id')) {
            $providerTypeId = $request->provider_type_id;
            $providerEmails = Provider::where('provider_type_id', $providerTypeId)
                ->pluck('email');
            $query->whereIn('email', $providerEmails);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $users   = $query->latest()->paginate($perPage);

        // Enriquecer con datos del proveedor vinculado
        $users->getCollection()->transform(function ($user) {
            $provider = Provider::where('email', $user->email)
                ->with('providerType:id,name')
                ->first();

            $user->provider = $provider ? [
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

        $newStatus = $provider->status === 'active' ? 'inactive' : 'active';
        $provider->update(['status' => $newStatus]);

        return response()->json([
            'message'   => $newStatus === 'active'
                ? 'Cuenta del proveedor activada correctamente'
                : 'Cuenta del proveedor desactivada correctamente',
            'status'    => $newStatus,
            'is_active' => $newStatus === 'active',
        ]);
    }

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
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña restablecida correctamente']);
    }

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