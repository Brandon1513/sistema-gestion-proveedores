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
     * Login de usuarios internos
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 401);
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
        // Verificar invitación
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

            // Crear usuario para el proveedor
            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('proveedor');

            // Crear proveedor
            $provider = Provider::create([
                'provider_type_id' => $invitation->provider_type_id,
                'business_name' => $request->business_name,
                'rfc' => $request->rfc,
                'email' => $invitation->email,
                'status' => 'pending',
                'created_by' => $invitation->invited_by,
            ]);

            // Marcar invitación como aceptada
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