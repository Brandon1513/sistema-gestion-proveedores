<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Enviar link de restablecimiento por email
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo es requerido',
            'email.email'    => 'El correo no es válido',
        ]);

        // Verificar que el email existe (sin revelar si existe o no por seguridad)
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Respuesta genérica para no revelar si el email existe
            return response()->json([
                'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
            ]);
        }

        return response()->json([
            'message' => 'No se pudo enviar el correo. Intenta de nuevo más tarde.',
        ], 500);
    }

    /**
     * Restablecer la contraseña con el token
     * POST /api/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'token.required'             => 'El token es requerido',
            'email.required'             => 'El correo es requerido',
            'email.email'                => 'El correo no es válido',
            'password.required'          => 'La contraseña es requerida',
            'password.min'               => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed'         => 'Las contraseñas no coinciden',
            'password_confirmation.required' => 'Confirma tu nueva contraseña',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revocar todos los tokens de Sanctum al cambiar contraseña
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Contraseña restablecida exitosamente. Ya puedes iniciar sesión.',
            ]);
        }

        // Errores posibles: token inválido, email no encontrado, token expirado
        $errorMessages = [
            Password::INVALID_TOKEN => 'El enlace ha expirado o no es válido. Solicita uno nuevo.',
            Password::INVALID_USER  => 'No encontramos una cuenta con ese correo.',
        ];

        return response()->json([
            'message' => $errorMessages[$status] ?? 'No se pudo restablecer la contraseña.',
            'errors'  => ['token' => [$errorMessages[$status] ?? 'Token inválido']],
        ], 422);
    }
}