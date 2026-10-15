<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncHealth
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';
    // El planificador late cada minuto: más de 10 sin latir = cron caído
    public const HEARTBEAT_MAX_MINUTES = 10;
    // El sync completo corre cada 4 h: más de 6 h sin uno exitoso = algo falla
    public const SYNC_MAX_HOURS = 6;

    public function status(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);
        $beatAt = $beat ? Carbon::createFromTimestamp($beat) : null;

        // Solo syncs completos: los de un proveedor ("(proveedor N)") no cuentan,
        // para que el botón "Sincronizar ahora" no esconda un sync general muerto.
        $lastSyncRaw = DB::table('netsuite_sync_logs')
            ->where('module', 'invoices')
            ->where('status', 'exitoso')
            ->where('message', 'not like', '%(proveedor %')
            ->max('created_at');
        $lastSync = $lastSyncRaw ? Carbon::parse($lastSyncRaw) : null;

        $problems = [];
        if (!$beatAt || $beatAt->lt(now()->subMinutes(self::HEARTBEAT_MAX_MINUTES))) {
            $problems[] = 'scheduler_stopped';
        }
        if (!$lastSync || $lastSync->lt(now()->subHours(self::SYNC_MAX_HOURS))) {
            $problems[] = 'sync_stale';
        }

        return [
            'ok' => empty($problems),
            'problems' => $problems,
            'scheduler_last_beat_at' => $beatAt?->toIso8601String(),
            'last_full_sync_at' => $lastSync?->toIso8601String(),
        ];
    }
}