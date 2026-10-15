<?php

use App\Services\SyncHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

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
// El cron del servidor ejecuta schedule:run cada 5 minutos:
//   */5 * * * * cd /home/portalproveedores/public_html && php artisan schedule:run >> storage/logs/cron.log 2>&1
//
// REGLAS PARA AGREGAR TAREAS:
//  1. Programa SIEMPRE en minutos múltiplos de 5 (:05, :10, :25...). Una tarea
//     en un minuto como :07 nunca correría, porque schedule:run solo se
//     ejecuta en los múltiplos de 5.
//  2. Evita el minuto :00. Algo en el hosting quita entradas del crontab al
//     inicio de cada hora; ~/cron-guard.sh las repone en segundos, pero el
//     disparo de las :00 puede perderse.

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