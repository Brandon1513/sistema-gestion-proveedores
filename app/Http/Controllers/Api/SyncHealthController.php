<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncHealthController extends Controller
{
    /** Aviso en la pantalla de Finanzas: solo si los datos realmente están viejos. */
    public function finance(SyncHealth $health): JsonResponse
    {
        $status = $health->status();

        return response()->json([
            'stale' => in_array('sync_stale', $status['problems'], true),
            'last_full_sync_at' => $status['last_full_sync_at'],
        ]);
    }

    /** Monitor externo: 200 si todo late, 503 si no. Protegida con token. */
    public function publicCheck(Request $request, SyncHealth $health): JsonResponse
    {
        $expected = (string) config('services.health.token');
        $given = (string) $request->query('token', '');

        // Sin token configurado o incorrecto: 404, para no delatar que la ruta existe
        if ($expected === '' || !hash_equals($expected, $given)) {
            abort(404);
        }

        $status = $health->status();

        return response()->json($status, $status['ok'] ? 200 : 503);
    }
}