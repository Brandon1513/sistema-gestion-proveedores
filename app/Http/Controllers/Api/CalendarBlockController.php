<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarBlock;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarBlockController extends Controller
{
    /**
     * Listar bloques de un mes
     * GET /calendar-blocks?year=2026&month=7
     */
    public function index(Request $request): JsonResponse
    {
        $year  = $request->integer('year',  now()->year);
        $month = $request->integer('month', now()->month);

        $blocks = CalendarBlock::whereYear('block_date', $year)
            ->whereMonth('block_date', $month)
            ->with('createdBy:id,name')
            ->orderBy('block_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn($b) => $this->format($b));

        return response()->json(['blocks' => $blocks]);
    }

    /**
     * Crear bloque
     * POST /calendar-blocks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'block_date'   => 'required|date',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'title'        => 'required|string|max:255',
            'observations' => 'nullable|string|max:1000',
        ], [
            'end_time.after'          => 'La hora de fin debe ser después de la hora de inicio',
            'start_time.date_format'  => 'Formato de hora inválido',
            'end_time.date_format'    => 'Formato de hora inválido',
        ]);

        $block = CalendarBlock::create([
            'block_date'   => $validated['block_date'],
            'start_time'   => $validated['start_time'],
            'end_time'     => $validated['end_time'],
            'title'        => $validated['title'],
            'observations' => $validated['observations'] ?? null,
            'created_by'   => $request->user()->id,
        ]);

        // ✅ Notificar a Seguridad, Ingeniero de Alimentos y Admin/Super Admin
        NotificationDispatcher::notifyRoles(
            ['seguridad', 'ingeniero_alimentos', 'admin', 'super_admin'],
            $request->user()->id,
            'calendar_block_created',
            'Espacio bloqueado',
            "Se bloqueó el horario del " .
                Carbon::parse($block->block_date)->locale('es')->isoFormat('D [de] MMMM') .
                " de {$validated['start_time']} a {$validated['end_time']}: {$block->title}",
            ['block_id' => $block->id, 'link' => '/appointments']
        );

        return response()->json([
            'message' => 'Bloque creado correctamente',
            'block'   => $this->format($block->load('createdBy:id,name')),
        ], 201);
    }

    /**
     * Actualizar bloque
     * PUT /calendar-blocks/{id}
     */
    public function update(Request $request, CalendarBlock $calendarBlock): JsonResponse
    {
        $validated = $request->validate([
            'block_date'   => 'sometimes|date',
            'start_time'   => 'sometimes|date_format:H:i',
            'end_time'     => 'sometimes|date_format:H:i|after:start_time',
            'title'        => 'sometimes|string|max:255',
            'observations' => 'nullable|string|max:1000',
        ]);

        $calendarBlock->update($validated);

        return response()->json([
            'message' => 'Bloque actualizado correctamente',
            'block'   => $this->format($calendarBlock->load('createdBy:id,name')),
        ]);
    }

    /**
     * Eliminar bloque
     * DELETE /calendar-blocks/{id}
     */
    public function destroy(CalendarBlock $calendarBlock): JsonResponse
    {
        $calendarBlock->delete();
        return response()->json(['message' => 'Bloque eliminado correctamente']);
    }

    private function format(CalendarBlock $b): array
    {
        return [
            'id'           => $b->id,
            'block_date'   => $b->block_date instanceof \Carbon\Carbon
                ? $b->block_date->format('Y-m-d')
                : $b->block_date,
            'start_time'   => substr($b->start_time, 0, 5),
            'end_time'     => substr($b->end_time,   0, 5),
            'title'        => $b->title,
            'observations' => $b->observations,
            'created_by'   => $b->createdBy?->name,
        ];
    }
}