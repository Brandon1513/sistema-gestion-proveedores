<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpiration
{
    /**
     * Minutos de inactividad permitidos antes de expirar la sesión.
     * Configurable vía .env con SANCTUM_INACTIVITY_MINUTES.
     */
    protected function inactivityLimit(): int
    {
        return (int) config('sanctum.inactivity_expiration', 480); // 8 horas por defecto
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            // ⚠️ Importante: buscamos el token ANTES de que auth:sanctum lo
            // autentique, porque autenticar actualiza last_used_at. Si
            // revisáramos después de auth:sanctum, siempre veríamos el
            // timestamp recién refrescado y nunca expiraría.
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken) {
                $referenceTime = $accessToken->last_used_at ?? $accessToken->created_at;

                if ($referenceTime && $referenceTime->lt(now()->subMinutes($this->inactivityLimit()))) {
                    $accessToken->delete();

                    return response()->json([
                        'message'    => 'Tu sesión expiró por inactividad. Por favor inicia sesión nuevamente.',
                        'error_code' => 'SESSION_EXPIRED',
                    ], 401);
                }
            }
        }

        return $next($request);
    }
}