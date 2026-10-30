<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Disparador externo del planificador (cron-job.org).
 *
 * cron-job.org llama a esta ruta cada minuto. NO ejecutamos las tareas dentro
 * de la petición HTTP (cron-job.org corta a los 30 s y el sync de NetSuite
 * dura ~14 min): lanzamos `php artisan schedule:run` como proceso
 * independiente y respondemos de inmediato. Así el scheduler corre igual que
 * con un cron real del servidor.
 */
class CronTriggerController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $expected = (string) config('services.cron.token');
        $given    = (string) ($request->header('X-Cron-Token') ?? $request->query('token', ''));

        // Sin token configurado o incorrecto: 404, para no delatar que la ruta existe
        if ($expected === '' || !hash_equals($expected, $given)) {
            abort(404);
        }

        // Un solo disparo por minuto: protege contra reintentos o llamadas dobles
        $lock = Cache::lock('cron-trigger:' . now()->format('YmdHi'), 60);
        if (!$lock->get()) {
            return response()->json(['status' => 'skipped', 'reason' => 'already_triggered_this_minute']);
        }

        if (!function_exists('exec')) {
            Log::error('Cron trigger: la función exec() está deshabilitada en este servidor');
            return response()->json(['status' => 'error', 'reason' => 'exec_disabled'], 500);
        }

        $command = sprintf(
            'cd %s && nohup %s artisan schedule:run >> %s 2>&1 < /dev/null &',
            escapeshellarg(base_path()),
            escapeshellarg((string) config('services.cron.php_binary', 'php')),
            escapeshellarg(storage_path('logs/cron.log'))
        );

        exec($command);

        return response()->json([
            'status' => 'started',
            'at'     => now()->toIso8601String(),
        ], 202);
    }
}