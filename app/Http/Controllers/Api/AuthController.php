<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterProviderRequest;
use App\Models\User;
use App\Models\Provider;
use App\Models\ProviderInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Login de usuarios internos y proveedores
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        // ✅ Verificar si el usuario interno está activo
        if (isset($user->is_active) && !$user->is_active) {
            return response()->json([
                'message' => 'Tu cuenta está inactiva. Contacta al administrador del sistema.',
                'error_code' => 'ACCOUNT_INACTIVE',
                'contact_email' => 'brandon.devora@dasavena.com',
            ], 403);
        }

        // ✅ Si es proveedor, verificar el status de su empresa
        if ($user->hasRole('proveedor')) {
            $provider = Provider::where('email', $user->email)->first();

            if ($provider && $provider->status === 'inactive') {
                return response()->json([
                    'message' => 'Tu cuenta de proveedor está inactiva. Por favor contacta al administrador del sistema.',
                    'error_code' => 'PROVIDER_INACTIVE',
                    'contact_email' => 'brandon.devora@dasavena.com',
                ], 403);
            }

            if ($provider && $provider->status === 'rejected') {
                return response()->json([
                    'message' => 'Tu solicitud como proveedor fue rechazada. Por favor contacta al administrador del sistema.',
                    'error_code' => 'PROVIDER_REJECTED',
                    'contact_email' => 'brandon.devora@dasavena.com',
                ], 403);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'token' => $token,
        ]);
    }

    /**
     * Registro de proveedor con token de invitación
     */
    public function registerProvider(RegisterProviderRequest $request): JsonResponse
    {
        $invitation = ProviderInvitation::where('token', $request->token)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || $invitation->is_expired) {
            return response()->json([
                'message' => 'Invitación inválida o expirada',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('proveedor');

            $provider = Provider::create([
                'provider_type_id' => $invitation->provider_type_id,
                'business_name' => $request->business_name,
                'rfc' => strtoupper($request->rfc),
                'email' => $invitation->email,
                'status' => 'pending',
                'created_by' => $invitation->invited_by,
            ]);

            $invitation->markAsAccepted($provider);

            DB::commit();

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registro exitoso',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                ],
                'provider' => [
                    'id' => $provider->id,
                    'business_name' => $provider->business_name,
                    'status' => $provider->status,
                ],
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar proveedor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso',
        ]);
    }

    /**
     * Usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }
}