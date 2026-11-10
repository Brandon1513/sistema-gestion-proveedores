<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NonConformity;
use App\Models\NonConformityEvidence;
use App\Models\NonConformityResponse;
use App\Support\RichText;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Indicadores y Excel de No Conformidades.
 * Acceso: super_admin, ingeniero_alimentos y compras.
 *
 * "A tiempo" (igual que el Excel de Calidad):
 *  - Respuesta: primer plan enviado a más tardar en su fecha límite. Tarde si se envió
 *    después o si no ha respondido y ya venció. Las que no han vencido no cuentan.
 *  - Cierre: cerrada a más tardar en el límite de evidencias. Tarde si se cerró después
 *    o si sigue abierta con el plazo vencido.
 */
class NonConformityReportController extends Controller
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private function filtered(Request $request): Collection
    {
        $request->validate([
            'year'        => 'nullable|integer|min:2020|max:2100',
            'month'       => 'nullable|integer|min:1|max:12',
            'provider_id' => 'nullable|exists:providers,id',
        ]);

        $year = (int) ($request->year ?: now()->year);

        $query = NonConformity::with([
                'provider:id,business_name,rfc',
                'productService:id,name',
                'unit:id,abbreviation',
                'type:id,name',
                'closedBy:id,name',
                'latestResponse',
                'acceptedResponse.actions.evidences',
                'responses.reviewedBy:id,name',
            ])
            ->whereYear('deviation_date', $year);

        if ($request->filled('month'))       $query->whereMonth('deviation_date', $request->month);
        if ($request->filled('provider_id')) $query->where('provider_id', $request->provider_id);

        return $query->orderBy('folio_year')->orderBy('folio_sequence')->get();
    }

    /** 'on_time' | 'late' | 'pending' */
    private function responseTiming(NonConformity $nc): string
    {
        if ($nc->response_received_at) {
            return $nc->response_received_at->lte($nc->response_due_date) ? 'on_time' : 'late';
        }
        return Carbon::today()->gt($nc->response_due_date) ? 'late' : 'pending';
    }

    /** 'on_time' | 'late' | 'pending' */
    private function closeTiming(NonConformity $nc): string
    {
        $limit = $nc->evidence_due_date ?? $nc->response_due_date;
        if ($nc->closed_at) {
            return $nc->closed_at->copy()->startOfDay()->lte($limit) ? 'on_time' : 'late';
        }
        return Carbon::today()->gt($limit) ? 'late' : 'pending';
    }

    private function pct(int $part, int $total): ?int
    {
        return $total > 0 ? (int) round($part / $total * 100) : null;
    }

    private function avg(Collection $values): ?float
    {
        $values = $values->filter(fn ($v) => $v !== null);
        return $values->isNotEmpty() ? round($values->avg(), 1) : null;
    }

    private function providerStats(Collection $ncs): Collection
    {
        return $ncs->groupBy('provider_id')->map(function ($group) {
            $resp  = $group->map(fn ($nc) => $this->responseTiming($nc));
            $close = $group->map(fn ($nc) => $this->closeTiming($nc));
            $first = $group->first();

            return [
                'provider_id'        => $first->provider_id,
                'provider_name'      => $first->provider?->business_name,
                'rfc'                => $first->provider?->rfc,
                'total'              => $group->count(),
                'response_on_time'   => $resp->filter(fn ($t) => $t === 'on_time')->count(),
                'response_late'      => $resp->filter(fn ($t) => $t === 'late')->count(),
                'closed_on_time'     => $group->filter(fn ($nc) => $nc->closed_at && $this->closeTiming($nc) === 'on_time')->count(),
                'closed_late'        => $group->filter(fn ($nc) => $nc->closed_at && $this->closeTiming($nc) === 'late')->count(),
                'open'               => $group->whereNull('closed_at')->count(),
                'open_overdue'       => $group->filter(fn ($nc) => !$nc->closed_at && $this->closeTiming($nc) === 'late')->count(),
                'avg_response_days'  => $this->avg($group->map(fn ($nc) => $nc->response_received_at ? $nc->issued_at->diffInDays($nc->response_received_at) : null)),
                'response_on_time_pct' => $this->pct(
                    $resp->filter(fn ($t) => $t === 'on_time')->count(),
                    $resp->filter(fn ($t) => $t !== 'pending')->count()
                ),
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * GET /non-conformities/stats?year=&month=&provider_id=
     */
    public function stats(Request $request): JsonResponse
    {
        $ncs   = $this->filtered($request);
        $resp  = $ncs->map(fn ($nc) => $this->responseTiming($nc));
        $close = $ncs->map(fn ($nc) => $this->closeTiming($nc));

        $byStatus = collect(NonConformity::DISPLAY_STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $ncs->filter(fn ($nc) => $nc->display_status === $s)->count()]);

        $byType = $ncs->groupBy(fn ($nc) => $nc->type?->name ?? 'Sin tipo')
            ->map(fn ($g, $name) => ['name' => $name, 'count' => $g->count()])
            ->sortByDesc('count')->values();

        $byMonth = collect(range(1, 12))->map(fn ($m) => [
            'month' => self::MONTHS[$m - 1],
            'count' => $ncs->filter(fn ($nc) => (int) $nc->deviation_date->month === $m)->count(),
        ]);

        $respDecided  = $resp->filter(fn ($t) => $t !== 'pending')->count();
        $closeDecided = $close->filter(fn ($t) => $t !== 'pending')->count();

        return response()->json([
            'year'    => (int) ($request->year ?: now()->year),
            'summary' => [
                'total'                => $ncs->count(),
                'open'                 => $ncs->whereNull('closed_at')->count(),
                'closed'               => $ncs->whereNotNull('closed_at')->count(),
                'response_on_time'     => $resp->filter(fn ($t) => $t === 'on_time')->count(),
                'response_late'        => $resp->filter(fn ($t) => $t === 'late')->count(),
                'response_on_time_pct' => $this->pct($resp->filter(fn ($t) => $t === 'on_time')->count(), $respDecided),
                'close_on_time'        => $close->filter(fn ($t) => $t === 'on_time')->count(),
                'close_late'           => $close->filter(fn ($t) => $t === 'late')->count(),
                'close_on_time_pct'    => $this->pct($close->filter(fn ($t) => $t === 'on_time')->count(), $closeDecided),
                'avg_response_days'    => $this->avg($ncs->map(fn ($nc) => $nc->response_received_at ? $nc->issued_at->diffInDays($nc->response_received_at) : null)),
                'avg_close_days'       => $this->avg($ncs->map(fn ($nc) => $nc->closed_at ? $nc->issued_at->diffInDays($nc->closed_at->copy()->startOfDay()) : null)),
            ],
            'by_status'   => $byStatus,
            'by_type'     => $byType,
            'by_month'    => $byMonth,
            'by_provider' => $this->providerStats($ncs),
        ]);
    }

    // ── Excel ────────────────────────────────────────────────────────────────

    private function styleSheet($sheet, int $columns, array $wrapColumns = []): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6A2C75');
        $sheet->getStyle("A1:{$last}1")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$last}1");

        for ($i = 1; $i <= $columns; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            if (in_array($i, $wrapColumns, true)) {
                $sheet->getColumnDimension($col)->setWidth(50);
                $sheet->getStyle("{$col}2:{$col}" . max(2, $sheet->getHighestRow()))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }

    private const TIMING_LABELS = ['on_time' => 'A tiempo', 'late' => 'Fuera de tiempo', 'pending' => 'En plazo'];

    /**
     * GET /non-conformities/export?year=&month=&provider_id=
     */
    public function export(Request $request)
    {
        $ncs = $this->filtered($request);
        $d   = fn ($date) => $date ? Carbon::parse($date)->format('Y-m-d') : '';

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: NC (columnas de la hoja de Calidad + detalle) ─────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NC');
        $headers = [
            'Mes', 'Fecha', 'Folio', 'Proveedor', 'RFC', 'Producto', 'Lote', 'Tipo',
            'Motivo / Descripción', 'Cantidad involucrada', 'Fecha de recepción',
            'Acción inmediata', 'Fecha de acción inmediata',
            'Fecha de emisión', 'Fecha límite de envío AC', 'Fecha de recibo AC', 'Respuesta',
            'Vueltas del plan', 'Fecha límite de envío EAC', 'Evidencias aceptadas',
            'STATUS', 'Etapa', 'Cierre', 'Fecha de cierre', 'Cerrada por', 'Comentario de cierre',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($ncs as $nc) {
            $progress = $nc->evidence_progress;
            $qty = $nc->quantity_involved !== null
                ? rtrim(rtrim(number_format((float) $nc->quantity_involved, 2), '0'), '.') . ' ' . ($nc->unit?->abbreviation ?? '')
                : '';

            $sheet->fromArray([
                (int) $nc->deviation_date->month,
                $d($nc->deviation_date),
                $nc->folio,
                $nc->provider?->business_name,
                $nc->provider?->rfc,
                $nc->productService?->name,
                $nc->lot,
                $nc->type?->name,
                RichText::toPlain($nc->description),
                trim($qty),
                $d($nc->reception_date),
                $nc->immediate_action,
                $d($nc->immediate_action_date),
                $d($nc->issued_at),
                $d($nc->response_due_date),
                $d($nc->response_received_at),
                self::TIMING_LABELS[$this->responseTiming($nc)],
                $nc->responses->count(),
                $d($nc->evidence_due_date),
                $progress ? "{$progress['accepted']} de {$progress['total']}" : '',
                $nc->display_status,
                $nc->stage_label,
                self::TIMING_LABELS[$this->closeTiming($nc)],
                $nc->closed_at?->format('Y-m-d H:i') ?? '',
                $nc->closedBy?->name,
                $nc->closing_comment,
            ], null, "A{$row}");
            $row++;
        }
        $this->styleSheet($sheet, count($headers), [9, 12, 26]);

        // ── Hoja 2: Vueltas del plan ────────────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Vueltas del plan');
        $headers2 = [
            'Folio', 'Proveedor', 'Vuelta', 'Enviada', 'Líder del equipo', 'Área',
            'Causa raíz', 'Acciones', 'Resultado', 'Fecha de revisión', 'Revisó', 'Comentario de revisión',
        ];
        $sheet2->fromArray($headers2, null, 'A1');
        $row = 2;
        foreach ($ncs as $nc) {
            foreach ($nc->responses->sortBy('round') as $r) {
                $sheet2->fromArray([
                    $nc->folio,
                    $nc->provider?->business_name,
                    $r->round,
                    $r->submitted_at?->format('Y-m-d H:i'),
                    $r->team_leader,
                    $r->area,
                    RichText::toPlain($r->root_cause),
                    $r->actions()->count(),
                    NonConformityResponse::REVIEW_LABELS[$r->review_status] ?? $r->review_status,
                    $r->reviewed_at?->format('Y-m-d H:i') ?? '',
                    $r->reviewedBy?->name,
                    $r->review_comment,
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->styleSheet($sheet2, count($headers2), [7, 12]);

        // ── Hoja 3: Acciones y evidencias (vuelta aceptada) ─────────────────
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Acciones y evidencias');
        $headers3 = [
            'Folio', 'Proveedor', 'Vuelta', '#', 'Acción', 'Responsable', 'Fecha compromiso',
            'Fecha de realización', 'Evidencia a entregar', 'Intentos', 'Último archivo',
            'Fecha de envío', 'Cumple / No cumple', 'Comentario de revisión',
        ];
        $sheet3->fromArray($headers3, null, 'A1');
        $row = 2;
        foreach ($ncs as $nc) {
            $accepted = $nc->acceptedResponse;
            if (!$accepted) continue;
            foreach ($accepted->actions as $a) {
                $ev = $a->evidences->first();
                $sheet3->fromArray([
                    $nc->folio,
                    $nc->provider?->business_name,
                    $accepted->round,
                    $a->sort_order,
                    $a->action,
                    $a->responsible,
                    $d($a->commitment_date),
                    $d($a->completion_date),
                    $a->evidence_to_deliver,
                    $a->evidences->count(),
                    $ev?->original_name ?? '',
                    $ev?->submitted_at?->format('Y-m-d H:i') ?? '',
                    $ev ? (NonConformityEvidence::REVIEW_LABELS[$ev->review_status] ?? $ev->review_status) : 'Pendiente de entregar',
                    $ev?->review_comment,
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->styleSheet($sheet3, count($headers3), [5, 9, 14]);

        // ── Hoja 4: Por proveedor ───────────────────────────────────────────
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Por proveedor');
        $headers4 = [
            'Proveedor', 'RFC', 'NC', 'Respondidas a tiempo', 'Respondidas fuera de tiempo', '% respuesta a tiempo',
            'Cerradas a tiempo', 'Cerradas fuera de tiempo', 'Abiertas', 'Abiertas vencidas', 'Promedio de días para responder',
        ];
        $sheet4->fromArray($headers4, null, 'A1');
        $row = 2;
        foreach ($this->providerStats($ncs) as $p) {
            $sheet4->fromArray([
                $p['provider_name'], $p['rfc'], $p['total'],
                $p['response_on_time'], $p['response_late'],
                $p['response_on_time_pct'] !== null ? $p['response_on_time_pct'] . '%' : '',
                $p['closed_on_time'], $p['closed_late'], $p['open'], $p['open_overdue'],
                $p['avg_response_days'] ?? '',
            ], null, "A{$row}");
            $row++;
        }
        $this->styleSheet($sheet4, count($headers4));

        $spreadsheet->setActiveSheetIndex(0);

        $year     = (int) ($request->year ?: now()->year);
        $filename = "no_conformidades_{$year}" . ($request->filled('month') ? '_' . str_pad($request->month, 2, '0', STR_PAD_LEFT) : '') . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}