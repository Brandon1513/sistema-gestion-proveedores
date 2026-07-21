<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    private const PREVIEW_LIMIT = 50;

    // ═══════════════════════════════════════════════════════════════════════
    // ── REPORTE 1: Citas / Recepciones ──────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════

    private function buildAppointmentsQuery(Request $request): Builder
    {
        $query = Appointment::with([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'scheduledBy:id,name',
            'receptionReviewedBy:id,name',
            'rescheduledFrom:id,appointment_date,appointment_time',
            'items.productService:id,name,type',
            'items.unit:id,abbreviation',
            'items.receivedUnit:id,abbreviation',
        ]);

        if ($request->filled('date_from'))   $query->whereDate('appointment_date', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('appointment_date', '<=', $request->date_to);
        if ($request->filled('provider_id')) $query->where('provider_id', $request->provider_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('reception_status')) $query->where('reception_status', $request->reception_status);

        return $query;
    }

    private function validateAppointmentFilters(Request $request): void
    {
        $request->validate([
            'date_from'        => 'nullable|date',
            'date_to'          => 'nullable|date',
            'provider_id'      => 'nullable|exists:providers,id',
            'status'           => 'nullable|string',
            'reception_status' => 'nullable|string',
        ]);
    }

    /**
     * Vista previa en JSON — mismos filtros que el export, limitado a
     * PREVIEW_LIMIT filas.
     * GET /reports/appointments/preview
     */
    public function appointmentsPreview(Request $request)
    {
        $this->validateAppointmentFilters($request);

        $query = $this->buildAppointmentsQuery($request);
        $total = (clone $query)->count();

        $appointments = $query
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $rows = $appointments->map(function ($a) {
            $items             = $a->items;
            $notDeliveredCount = $items->where('reception_status', 'not_delivered')->count();

            return [
                'id'                  => $a->id,
                'date'                => $a->appointment_date?->format('Y-m-d'),
                'time'                => substr($a->appointment_time, 0, 5),
                'type_label'          => $a->type_label,
                'provider_name'       => $a->provider?->business_name,
                'status'              => $a->status,
                'status_label'        => $a->status_label,
                'is_rescheduled'      => (bool) $a->rescheduled_to_id,
                'rescheduled_from'    => $a->rescheduledFrom
                    ? $a->rescheduledFrom->appointment_date?->format('Y-m-d') . ' ' . substr($a->rescheduledFrom->appointment_time, 0, 5)
                    : null,
                'reception_status'    => $a->reception_status ?? 'pending',
                'reception_label'     => $a->reception_label,
                'items_total'         => $items->count(),
                'items_not_delivered' => $notDeliveredCount,
                'has_missing_docs'    => (bool) $a->has_missing_docs,
            ];
        });

        return response()->json([
            'total'   => $total,
            'showing' => $rows->count(),
            'rows'    => $rows,
        ]);
    }

    /**
     * Exporta un reporte de citas + recepciones a Excel.
     * GET /reports/appointments/export
     */
    public function appointmentsExport(Request $request)
    {
        $this->validateAppointmentFilters($request);

        $appointments = $this->buildAppointmentsQuery($request)
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: Resumen de citas ────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Citas');

        $headers = [
            'ID', 'Fecha', 'Hora', 'Tipo', 'Proveedor', 'RFC', 'Tipo Proveedor',
            'Estado Cita', 'Agendada por', 'Reagendada de',
            'Entrada Confirmada', 'Hora Llegada', 'Llegó a Tiempo', 'Docs Faltantes',
            'No se Presentó — Notas',
            'Estado Recepción', 'Revisado por', 'Fecha Revisión', 'Observaciones Recepción',
            'Total Ítems', 'Ítems No Entregados',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($appointments as $a) {
            $items             = $a->items;
            $notDeliveredCount = $items->where('reception_status', 'not_delivered')->count();

            $sheet->fromArray([
                $a->id,
                $a->appointment_date?->format('Y-m-d'),
                substr($a->appointment_time, 0, 5),
                $a->type_label,
                $a->provider?->business_name,
                $a->provider?->rfc,
                $a->provider?->providerType?->name,
                $a->status_label,
                $a->scheduledBy?->name,
                $a->rescheduledFrom
                    ? $a->rescheduledFrom->appointment_date?->format('Y-m-d') . ' ' . substr($a->rescheduledFrom->appointment_time, 0, 5)
                    : '',
                $a->is_entry_confirmed ? 'Sí' : 'No',
                $a->actual_arrival_time ? substr($a->actual_arrival_time, 0, 5) : '',
                is_null($a->arrived_on_time) ? '' : ($a->arrived_on_time ? 'Sí' : 'No'),
                $a->has_missing_docs ? 'Sí' : 'No',
                $a->no_show_notes,
                $a->reception_label,
                $a->receptionReviewedBy?->name,
                $a->reception_reviewed_at?->format('Y-m-d H:i'),
                $a->reception_notes,
                $items->count(),
                $notDeliveredCount,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);
        $sheet->getStyle('A1:U1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->freezePane('A2');

        // ── Hoja 2: Detalle por producto ─────────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detalle Productos');

        $headers2 = [
            'Cita ID', 'Fecha', 'Proveedor', 'Producto/Servicio', 'Tipo',
            'Cant. Esperada', 'Unidad Esperada', 'No Entregado',
            'Cant. Recibida', 'Unidad Recibida', 'Cant. Rechazada', 'Cant. Aceptada',
            'Estado Ítem', 'Motivo Rechazo', 'Observaciones',
        ];
        $sheet2->fromArray($headers2, null, 'A1');

        $row2 = 2;
        foreach ($appointments as $a) {
            foreach ($a->items as $item) {
                $rec = (float) ($item->quantity_received ?? 0);
                $rej = (float) ($item->quantity_rejected ?? 0);
                $acc = max(0, $rec - $rej);

                $sheet2->fromArray([
                    $a->id,
                    $a->appointment_date?->format('Y-m-d'),
                    $a->provider?->business_name,
                    $item->productService?->name,
                    $item->productService?->type === 'product' ? 'Producto' : 'Servicio',
                    $item->quantity_expected,
                    $item->unit?->abbreviation,
                    $item->not_delivered ? 'Sí' : 'No',
                    $item->not_delivered ? '' : $item->quantity_received,
                    $item->receivedUnit?->abbreviation,
                    $item->quantity_rejected,
                    $item->not_delivered ? '' : $acc,
                    $item->reception_status_label,
                    $item->rejection_reason_label,
                    $item->reception_notes,
                ], null, "A{$row2}");
                $row2++;
            }
        }

        foreach (range('A', 'O') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet2->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet2->freezePane('A2');

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'reporte_citas_' . now()->format('Y-m-d_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ── REPORTE 2: Cumplimiento Documental por Proveedor ────────────────────
    // ═══════════════════════════════════════════════════════════════════════

    private function validateComplianceFilters(Request $request): void
    {
        $request->validate([
            'provider_id'      => 'nullable|exists:providers,id',
            'provider_type_id' => 'nullable|exists:provider_types,id',
            'provider_status'  => 'nullable|string',
            'compliance'       => 'nullable|in:all,incomplete,expired',
        ]);
    }

    private function buildProvidersQuery(Request $request): Builder
    {
        $query = Provider::with(['providerType:id,name', 'documents']);

        if ($request->filled('provider_id'))      $query->where('id', $request->provider_id);
        if ($request->filled('provider_type_id')) $query->where('provider_type_id', $request->provider_type_id);
        if ($request->filled('provider_status'))  $query->where('status', $request->provider_status);

        return $query;
    }

    /**
     * Documentos requeridos para un proveedor según su tipo y tipo de persona.
     */
    private function getRequiredDocTypesForProvider(Provider $provider)
    {
        return DB::table('document_type_provider_type as dtpt')
            ->join('document_types as dt', 'dt.id', '=', 'dtpt.document_type_id')
            ->where('dtpt.provider_type_id', $provider->provider_type_id)
            ->where('dtpt.applies_to_existing', true)
            ->where(function ($q) use ($provider) {
                $q->where('dtpt.applies_to_persona', 'all')
                  ->orWhere('dtpt.applies_to_persona', $provider->tipo_persona);
            })
            ->where('dt.is_active', true)
            ->orderBy('dtpt.sort_order')
            ->select('dt.id as document_type_id', 'dt.name', 'dtpt.is_required')
            ->get();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'approved'     => 'Aprobado',
            'pending'      => 'Pendiente',
            'rejected'     => 'Rechazado',
            'expired'      => 'Vencido',
            'not_uploaded' => 'No subido',
            default        => $status,
        };
    }

    /**
     * Calcula el cumplimiento documental de un proveedor: documentos
     * requeridos vs. subidos/aprobados/pendientes/vencidos.
     */
    private function buildProviderCompliance(Provider $provider): array
    {
        $requiredTypes = $this->getRequiredDocTypesForProvider($provider);

        $details       = [];
        $requiredCount = 0;
        $approvedCount = 0;
        $missingCount  = 0;
        $pendingCount  = 0;
        $expiredCount  = 0;

        foreach ($requiredTypes as $rt) {
            $doc = $provider->documents
                ->where('document_type_id', $rt->document_type_id)
                ->sortByDesc('created_at')
                ->first();

            $isExpired = $doc && $doc->expiry_date && Carbon::now()->isAfter($doc->expiry_date);
            $status    = $doc ? ($isExpired ? 'expired' : $doc->status) : 'not_uploaded';

            if ($rt->is_required) {
                $requiredCount++;
                match ($status) {
                    'approved'                 => $approvedCount++,
                    'expired'                  => $expiredCount++,
                    'pending'                  => $pendingCount++,
                    'not_uploaded', 'rejected' => $missingCount++,
                    default                    => null,
                };
            }

            $details[] = [
                'document_type_id'  => $rt->document_type_id,
                'document_name'     => $rt->name,
                'is_required'       => (bool) $rt->is_required,
                'uploaded'          => (bool) $doc,
                'status'            => $status,
                'status_label'      => $this->statusLabel($status),
                'expiry_date'       => $doc?->expiry_date?->format('Y-m-d'),
                'days_until_expiry' => $doc?->days_until_expiry,
            ];
        }

        $compliancePct = $requiredCount > 0 ? round(($approvedCount / $requiredCount) * 100) : 100;

        return [
            'required_count' => $requiredCount,
            'approved_count' => $approvedCount,
            'missing_count'  => $missingCount,
            'pending_count'  => $pendingCount,
            'expired_count'  => $expiredCount,
            'compliance_pct' => $compliancePct,
            'details'        => $details,
        ];
    }

    private function passesComplianceFilter(Request $request, array $compliance): bool
    {
        if (!$request->filled('compliance') || $request->compliance === 'all') return true;

        if ($request->compliance === 'incomplete') {
            return $compliance['missing_count'] > 0 || $compliance['pending_count'] > 0;
        }
        if ($request->compliance === 'expired') {
            return $compliance['expired_count'] > 0;
        }
        return true;
    }

    /**
     * Vista previa en JSON — resumen de cumplimiento por proveedor.
     * GET /reports/providers-compliance/preview
     */
    public function providersCompliancePreview(Request $request)
    {
        $this->validateComplianceFilters($request);

        $providers = $this->buildProvidersQuery($request)->get();

        $rows = collect();
        foreach ($providers as $p) {
            $c = $this->buildProviderCompliance($p);
            if (!$this->passesComplianceFilter($request, $c)) continue;

            $rows->push([
                'id'             => $p->id,
                'business_name'  => $p->business_name,
                'rfc'            => $p->rfc,
                'provider_type'  => $p->providerType?->name,
                'status'         => $p->status,
                'required_count' => $c['required_count'],
                'approved_count' => $c['approved_count'],
                'pending_count'  => $c['pending_count'],
                'missing_count'  => $c['missing_count'],
                'expired_count'  => $c['expired_count'],
                'compliance_pct' => $c['compliance_pct'],
            ]);
        }

        $total   = $rows->count();
        $limited = $rows->values()->take(self::PREVIEW_LIMIT);

        return response()->json([
            'total'   => $total,
            'showing' => $limited->count(),
            'rows'    => $limited,
        ]);
    }

    /**
     * Exporta el reporte de cumplimiento documental a Excel.
     * GET /reports/providers-compliance/export
     */
    public function providersComplianceExport(Request $request)
    {
        $this->validateComplianceFilters($request);

        $providers = $this->buildProvidersQuery($request)->get();

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: Resumen por proveedor ────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen Proveedores');

        $headers = [
            'ID', 'Proveedor', 'RFC', 'Tipo Proveedor', 'Estado Proveedor',
            'Docs Requeridos', 'Aprobados', 'Pendientes', 'Faltantes', 'Vencidos',
            '% Cumplimiento',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row2Start = 2;
        $summaryRow = $row2Start;
        $detailRowsData = [];

        foreach ($providers as $p) {
            $c = $this->buildProviderCompliance($p);
            if (!$this->passesComplianceFilter($request, $c)) continue;

            $sheet->fromArray([
                $p->id,
                $p->business_name,
                $p->rfc,
                $p->providerType?->name,
                $p->status,
                $c['required_count'],
                $c['approved_count'],
                $c['pending_count'],
                $c['missing_count'],
                $c['expired_count'],
                $c['compliance_pct'] . '%',
            ], null, "A{$summaryRow}");
            $summaryRow++;

            foreach ($c['details'] as $d) {
                $detailRowsData[] = [
                    $p->id,
                    $p->business_name,
                    $p->rfc,
                    $d['document_name'],
                    $d['is_required'] ? 'Sí' : 'No',
                    $d['uploaded'] ? 'Sí' : 'No',
                    $d['status_label'],
                    $d['expiry_date'] ?? '',
                    $d['days_until_expiry'] ?? '',
                ];
            }
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->freezePane('A2');

        // ── Hoja 2: Detalle por documento ─────────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detalle Documentos');

        $headers2 = [
            'Proveedor ID', 'Proveedor', 'RFC', 'Documento', 'Requerido',
            'Subido', 'Estado', 'Fecha Vigencia', 'Días para Vencer',
        ];
        $sheet2->fromArray($headers2, null, 'A1');

        $row3 = 2;
        foreach ($detailRowsData as $d) {
            $sheet2->fromArray($d, null, "A{$row3}");
            $row3++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet2->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet2->freezePane('A2');

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'reporte_cumplimiento_documental_' . now()->format('Y-m-d_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}