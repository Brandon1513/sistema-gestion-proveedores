<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    protected const ALLOWED_DOMAIN = 'dasavena.com';
    protected const DEFAULT_ROLE = 'emp_solicitante';

    /**
     * Redirige al navegador hacia el login de Microsoft.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    /**
     * Microsoft regresa aquí después del login. Valida dominio,
     * crea/vincula el usuario, y redirige al frontend con un
     * código de intercambio de un solo uso (no el token directo).
     */
    public function callback(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (\Exception $e) {
            // 🔍 TEMPORAL — nos deja ver la causa real mientras depuramos
            Log::error('Microsoft OAuth callback falló', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect("{$frontendUrl}/login?ms_error=auth_failed");
        }

        $email = strtolower($microsoftUser->getEmail());

        if (!$email || !str_ends_with($email, '@' . self::ALLOWED_DOMAIN)) {
            return redirect("{$frontendUrl}/login?ms_error=domain_not_allowed");
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name'              => $microsoftUser->getName() ?: $email,
                'email'             => $email,
                'password'          => bcrypt(Str::random(40)), // no se usa, login es solo por SSO
                'microsoft_id'      => $microsoftUser->getId(),
                'email_verified_at' => now(),
            ]);

            $user->assignRole(self::DEFAULT_ROLE);
        } elseif (!$user->microsoft_id) {
            // Ya existía (ej. creado manualmente por admin) — lo vinculamos
            $user->update(['microsoft_id' => $microsoftUser->getId()]);
        }

        if (isset($user->is_active) && !$user->is_active) {
            return redirect("{$frontendUrl}/login?ms_error=account_inactive");
        }

        $exchangeCode = Str::random(64);
        Cache::put("ms_exchange:{$exchangeCode}", $user->id, now()->addSeconds(60));

        return redirect("{$frontendUrl}/auth/microsoft/callback?code={$exchangeCode}");
    }

    /**
     * El frontend llama a esto con el código para obtener el token real.
     */
    public function exchange(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $userId = Cache::pull("ms_exchange:{$request->code}"); // un solo uso

        if (!$userId) {
            return response()->json(['message' => 'Código inválido o expirado'], 401);
        }

        $user = User::findOrFail($userId);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'token' => $token,
        ]);
    }
}