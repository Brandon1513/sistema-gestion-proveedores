<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckTokenExpiration;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ CORS primero que todo
        $middleware->use([
            HandleCors::class,
        ]);

        $middleware->statefulApi();
        
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'check.token.expiration' => CheckTokenExpiration::class,
        ]);

        // ⚠️ Laravel reordena los middleware según su lista de prioridad, y
        // Authenticate (auth:sanctum) está en ella: sin esto se ejecuta ANTES
        // que CheckTokenExpiration sin importar el orden escrito en las rutas,
        // actualiza last_used_at y la sesión nunca expira por inactividad.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: CheckTokenExpiration::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();