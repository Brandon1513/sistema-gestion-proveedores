<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Obtener perfil del usuario autenticado
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->roles->pluck('name'),
                'is_active'   => $user->is_active,
                'created_at'  => $user->created_at,
            ],
        ]);
    }

    /**
     * Actualizar nombre y email del usuario autenticado
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ], [
            'name.required'  => 'El nombre es requerido',
            'email.required' => 'El correo es requerido',
            'email.email'    => 'El correo no es válido',
            'email.unique'   => 'Este correo ya está en uso',
        ]);

        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

    /**
     * Cambiar contraseña del usuario autenticado
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ], [
            'current_password.required' => 'La contraseña actual es requerida',
            'password.required'         => 'La nueva contraseña es requerida',
            'password.min'              => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed'        => 'Las contraseñas no coinciden',
        ]);

        // ✅ Verificar que la contraseña actual sea correcta
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta',
                'errors'  => ['current_password' => ['La contraseña actual es incorrecta']],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente',
        ]);
    }
}