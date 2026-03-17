<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

/*
|--------------------------------------------------------------------------
| Task Scheduling
|--------------------------------------------------------------------------
|
| Aquí se definen las tareas programadas que se ejecutarán automáticamente.
| Asegúrate de configurar el cron job en tu servidor:
| * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Verificar documentos próximos a vencer todos los días a las 8:00 AM
Schedule::command('documents:check-expiring --send-emails')
    ->dailyAt('08:00')
    ->timezone('America/Mexico_City')
    ->emailOutputOnFailure('brandon.devora@dasavena.com'); // Notificar al admin si falla

// Verificar documentos críticos (7 días) cada día a las 9:00 AM
Schedule::command('documents:check-expiring --days=7 --send-emails')
    ->dailyAt('09:00')
    ->timezone('America/Mexico_City')
    ->emailOutputOnFailure('brandon.devora@dasavena.com');

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