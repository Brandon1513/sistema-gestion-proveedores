<?php

use App\Services\SyncHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your console based routes.
| These routes are loaded by the ConsoleKernel and will be registered
| with the Artisan CLI. Enjoy building your amazing commands!
|
*/

// Comando de inspiración
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// Task Scheduling
// ---------------------------------------------------------------------------
//
// El planificador lo dispara cron-job.org CADA MINUTO llamando a:
//   GET /api/cron/run   (header X-Cron-Token)
// CronTriggerController lanza `php artisan schedule:run` en segundo plano
// (salida en storage/logs/cron.log) y responde de inmediato.
//
// Ya NO se usa el crontab del servidor ni ~/cron-guard.sh: si se reactivan
// junto con cron-job.org, las tareas sin withoutOverlapping() correrían dos
// veces (p. ej. correos de vencimiento duplicados).
//
// Al disparar cada minuto, las tareas pueden programarse en cualquier minuto.
// Las tareas largas deben llevar withoutOverlapping().

// Latido del planificador: lo lee /api/health/scheduler (monitor externo).
// Va primero para que ninguna tarea larga de este mismo disparo lo retrase.
Schedule::call(fn () => Cache::forever(SyncHealth::HEARTBEAT_KEY, now()->timestamp))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// Verificar documentos próximos a vencer todos los días a las 8:05 AM
Schedule::command('documents:check-expiring --send-emails')
    ->dailyAt('08:05')
    ->timezone('America/Mexico_City')
    ->emailOutputOnFailure('brandon.devora@dasavena.com'); // Notificar al admin si falla

// Verificar documentos críticos (7 días) cada día a las 9:05 AM
Schedule::command('documents:check-expiring --days=7 --send-emails')
    ->dailyAt('09:05')
    ->timezone('America/Mexico_City')
    ->emailOutputOnFailure('brandon.devora@dasavena.com');

// Vincular proveedores de SGP con vendors de NetSuite (por RFC)
Schedule::command('netsuite:reconcile-providers')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->emailOutputOnFailure('brandon.devora@dasavena.com');

// Sync completo de NetSuite: 00:05, 04:05, 08:05, 12:05, 16:05, 20:05.
// Dura ~14 min. El candado expira a los 30 min: si el proceso muere a medias,
// el siguiente ciclo no queda bloqueado (por defecto el candado dura 24 h).
Schedule::command('netsuite:sync-vendor-data')
    ->everyFourHours(5)
    ->withoutOverlapping(30);

// Enlazar facturas subidas por proveedores (UUID) — 20 min después del sync
Schedule::command('netsuite:link-invoice-submissions')
    ->everyFourHours(25)
    ->withoutOverlapping();

// Aviso de facturas vencidas — días hábiles
Schedule::command('netsuite:notify-overdue-invoices')
    ->dailyAt('08:10')
    ->weekdays()
    ->withoutOverlapping();

// Worker de colas (QUEUE_CONNECTION=database): procesa los jobs pendientes,
// p. ej. SendExpiringDocumentNotification. Se lanza cada minuto, se detiene
// al vaciar la cola o a los 50 s, y no se enciman dos workers. El candado
// expira a los 5 min por si un worker muere a medias.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Limpieza de tokens de sesión inactivos (mismo criterio que el middleware
// CheckTokenExpiration). sanctum:prune-expired no sirve aquí: solo borra
// tokens con expires_at o con 'expiration' configurado, y ambos están en null.
Schedule::call(function () {
    $limit = now()->subMinutes((int) config('sanctum.inactivity_expiration', 480));

    PersonalAccessToken::where(function ($q) use ($limit) {
        $q->where('last_used_at', '<', $limit)
          ->orWhere(function ($q2) use ($limit) {
              $q2->whereNull('last_used_at')->where('created_at', '<', $limit);
          });
    })->delete();
})
    ->dailyAt('02:15')
    ->name('prune-inactive-tokens')
    ->withoutOverlapping();

// ALTERNATIVAS (comentadas - descomenta si prefieres usarlas):

// Ejecutar cada 12 horas (8 AM y 8 PM)
// Schedule::command('documents:check-expiring --send-emails')
//     ->twiceDaily(8, 20)
//     ->timezone('America/Mexico_City')
//     ->emailOutputOnFailure('admin@sgp.local');

// Ejecutar solo días laborables
// Schedule::command('documents:check-expiring --send-emails')
//     ->dailyAt('08:00')
//     ->weekdays()
//     ->timezone('America/Mexico_City')
//     ->emailOutputOnFailure('admin@sgp.local');

// Verificar múltiples días a la vez
// Schedule::command('documents:check-expiring --days=60 --days=30 --days=15 --days=7 --send-emails')
//     ->dailyAt('08:00')
//     ->timezone('America/Mexico_City')
//     ->emailOutputOnFailure('admin@sgp.local');