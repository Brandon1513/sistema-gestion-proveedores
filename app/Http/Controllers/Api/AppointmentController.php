<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\ProductService;
use App\Models\Provider;
use App\Models\Unit;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AppointmentController extends Controller
{
    private const PROVIDER_TYPES_WITH_DOCS = [
        'Materias Primas y Material de Empaque',
        'Insumos Generales',
    ];

    private const PHYSICAL_DOCS = [
        'Materias Primas y Material de Empaque' => [
            ['key' => 'orden_compra',     'label' => 'Orden de compra',                            'required' => true ],
            ['key' => 'factura',          'label' => 'Factura',                                    'required' => false],
            ['key' => 'cert_calidad',     'label' => 'Certificado de calidad por lote',            'required' => true ],
            ['key' => 'cert_fumigacion',  'label' => 'Certificado de fumigación vigente (firmado)','required' => true ],
        ],
        'Insumos Generales' => [
            ['key' => 'orden_compra',     'label' => 'Orden de compra',                            'required' => true ],
            ['key' => 'factura',          'label' => 'Factura',                                    'required' => false],
            ['key' => 'cert_calidad',     'label' => 'Certificado de calidad por lote',            'required' => true ],
        ],
    ];

    // ── Compras / Admin ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'scheduledBy:id,name',
            'vehicle:id,brand_model,plates',
            'personnel:id,full_name,position',
            'entryConfirmedBy:id,name',
            'receptionReviewedBy:id,name',
            'rescheduledFrom:id,appointment_date,appointment_time', // ✅ nuevo
        ]);

        if ($request->filled('year') && $request->filled('month'))
            $query->forMonth((int)$request->year, (int)$request->month);
        if ($request->filled('date'))        $query->forDate($request->date);
        if ($request->filled('provider_id')) $query->where('provider_id', $request->provider_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('type'))        $query->where('type', $request->type);

        $appointments = $query->orderBy('appointment_date')->orderBy('appointment_time')->get();
        return response()->json([
            'appointments' => $appointments->map(fn($a) => $this->formatAppointment($a)),
            'total'        => $appointments->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('items') && is_string($request->items)) {
        $request->merge(['items' => json_decode($request->items, true)]);
    }
        $request->validate([
            'provider_id'      => 'required|exists:providers,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'duration_minutes' => 'nullable|integer|min:30|max:480',
            'type'             => 'required|in:entrega,residuos,auditoria,calibracion,servicio',
            'notes'            => 'nullable|string|max:2000',
            'status'           => 'nullable|in:scheduled,confirmed',
            'rescheduled_from_id' => 'nullable|exists:appointments,id', // ✅ nuevo
            // Items (múltiples productos)
            'items'            => 'nullable|array',
            'items.*.product_service_id' => 'required|exists:products_services,id',
            'items.*.quantity_expected'  => 'nullable|numeric|min:0',
            'items.*.unit_id'            => 'nullable|exists:units,id',
            'items.*.notes'              => 'nullable|string|max:500',
        ]);

        // ✅ Si viene rescheduled_from_id, evitar que la misma cita origen
        // se reagende dos veces (ya generó una cita nueva anteriormente).
        if ($request->filled('rescheduled_from_id')) {
            $origin = \App\Models\Appointment::find($request->rescheduled_from_id);
            if ($origin && $origin->rescheduled_to_id) {
                return response()->json([
                    'message' => 'Esta cita ya fue reagendada anteriormente',
                ], 422);
            }
        }
 
        $appointment = \App\Models\Appointment::create([
            'provider_id'         => $request->provider_id,
            'scheduled_by'        => auth()->id(),
            'appointment_date'    => $request->appointment_date,
            'appointment_time'    => $request->appointment_time,
            'duration_minutes'    => $request->duration_minutes ?? 60,
            'type'                => $request->type,
            'notes'               => $request->notes,
            'products'            => $request->products, // legacy, para compatibilidad
            'status'              => $request->status ?? 'scheduled',
            'rescheduled_from_id' => $request->rescheduled_from_id ?? null, // ✅ nuevo
        ]);

        // ✅ Cerrar el ciclo: marcar la cita origen como ya reagendada
        if ($request->filled('rescheduled_from_id')) {
        \App\Models\Appointment::where('id', $request->rescheduled_from_id)
            ->update(['rescheduled_to_id' => $appointment->id]);
    
        // ✅ NUEVO: notificar el reagendado
        $appointment->load('provider:id,business_name');
        NotificationDispatcher::notifyRoles(
            ['seguridad', 'ingeniero_alimentos', 'compras', 'admin', 'super_admin'],
            auth()->id(),
            'appointment_rescheduled',
            'Cita reagendada',
            "{$appointment->provider->business_name} fue reagendada para " .
                Carbon::parse($appointment->appointment_date)->locale('es')->isoFormat('D [de] MMMM') .
                " a las " . substr($appointment->appointment_time, 0, 5) . " hrs",
            ['appointment_id' => $appointment->id, 'link' => '/appointments']
        );
    }


 
        // Guardar adjunto si viene
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store("appointments/{$appointment->id}", 'documents');
            $appointment->update([
                'attachment_path' => $path,
                'attachment_name' => $request->file('attachment')->getClientOriginalName(),
            ]);
        }
 
        // Guardar ítems
        if ($request->filled('items')) {
            foreach ($request->items as $item) {
                AppointmentItem::create([
                    'appointment_id'     => $appointment->id,
                    'product_service_id' => $item['product_service_id'],
                    'quantity_expected'  => $item['quantity_expected'] ?? null,
                    'unit_id'            => $item['unit_id'] ?? null,
                    'notes'              => $item['notes'] ?? null,
                    'reception_status'   => 'pending',
                ]);
            }
        }
 
        return response()->json([
            'message'     => 'Cita agendada correctamente',
            'appointment' => $this->formatAppointment($appointment->fresh(['provider', 'items.productService', 'items.unit', 'rescheduledFrom'])),
        ], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'scheduledBy:id,name',
            'vehicle:id,brand_model,plates,color',
            'personnel:id,full_name,position',
            'cancelledBy:id,name',
            'entryConfirmedBy:id,name',
            'receptionReviewedBy:id,name',
            'rescheduledFrom:id,appointment_date,appointment_time', // ✅ nuevo
        ]);
        return response()->json(['appointment' => $this->formatAppointment($appointment)]);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        if ($request->has('items') && is_string($request->items)) {
            $request->merge(['items' => json_decode($request->items, true)]);
        }
        if (in_array($appointment->status, ['cancelled','completed']))
            return response()->json(['message'=>'No se puede modificar una cita cancelada o completada'], 422);

        $validated = $request->validate([
            'appointment_date' => ['sometimes','date','after_or_equal:today'],
            'appointment_time' => ['sometimes','date_format:H:i', function($attr,$value,$fail) use ($request,$appointment) {
                $date = $request->appointment_date ?? $appointment->appointment_date->format('Y-m-d');
                $this->validateBusinessHours($date, $value, $fail);
            }],
            'type'       => 'sometimes|in:entrega,residuos,auditoria,calibracion,servicio',
            'products'   => 'nullable|string|max:1000',
            'notes'      => 'nullable|string|max:1000',
            'duration_minutes' => 'sometimes|integer|min:30|max:480',
            'status'     => 'sometimes|in:scheduled,confirmed,completed',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        if ($request->hasFile('attachment')) {
            if ($appointment->attachment_path) Storage::disk('documents')->delete($appointment->attachment_path);
            $file = $request->file('attachment');
            $validated['attachment_path'] = $file->store('appointments','documents');
            $validated['attachment_name'] = $file->getClientOriginalName();
        }
        unset($validated['attachment']);
        $appointment->update($validated);

        // ✅ Sincronizar items
        if ($request->has('items') && is_array($request->items)) {
            $appointment->items()->delete();
            foreach ($request->items as $item) {
                if (!empty($item['product_service_id'])) {
                    $appointment->items()->create([
                        'product_service_id' => $item['product_service_id'],
                        'quantity_expected'  => $item['quantity_expected'] ?? null,
                        'unit_id'            => $item['unit_id'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['message'=>'Cita actualizada','appointment'=>$this->formatAppointment($appointment->fresh(['provider','scheduledBy','vehicle','personnel','items','rescheduledFrom']))]);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->status === 'cancelled') return response()->json(['message'=>'La cita ya está cancelada'], 422);
        if ($appointment->status === 'completed') return response()->json(['message'=>'No se puede cancelar una cita completada'], 422);

        $request->validate(['reason'=>'nullable|string|max:500']);
        $appointment->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->reason,
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
        ]);
        $this->notifyProvider($appointment->fresh(['provider:id,business_name,rfc,email']), 'cancelled');
        return response()->json(['message'=>'Cita cancelada']);
    }

    public function downloadAttachment(Appointment $appointment)
    {
        $user = auth()->user();
        if ($user->hasRole('proveedor')) {
            $provider = Provider::where('email', $user->email)->first();
            if (!$provider || $provider->id !== $appointment->provider_id)
                return response()->json(['message'=>'No autorizado'], 403);
        }
        if (!$appointment->attachment_path || !Storage::disk('documents')->exists($appointment->attachment_path))
            return response()->json(['message'=>'No hay archivo adjunto'], 404);
        return Storage::disk('documents')->download($appointment->attachment_path, $appointment->attachment_name ?? 'adjunto');
    }

    // ── Portal Proveedor ──────────────────────────────────────────────────────

    public function myIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        if (!$provider) return response()->json(['message'=>'Proveedor no encontrado'], 404);

        $base = Appointment::with(['scheduledBy:id,name','vehicle:id,brand_model,plates','personnel:id,full_name'])
            ->where('provider_id', $provider->id);

        $upcoming = (clone $base)->whereDate('appointment_date','>=',today())
            ->whereNotIn('status',['cancelled'])->orderBy('appointment_date')->orderBy('appointment_time')->get();
        $past = (clone $base)->where(function($q) {
            $q->whereDate('appointment_date','<',today())->orWhere('status','cancelled');
        })->orderByDesc('appointment_date')->orderByDesc('appointment_time')->limit(20)->get();

        return response()->json([
            'upcoming'       => $upcoming->map(fn($a) => $this->formatAppointment($a)),
            'past'           => $past->map(fn($a) => $this->formatAppointment($a)),
            'total_upcoming' => $upcoming->count(),
        ]);
    }

    public function providerComplete(Request $request, $appointmentId): JsonResponse
    {
        $user = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        if (!$provider) return response()->json(['message'=>'Proveedor no encontrado'], 404);

        $appointment = Appointment::where('id',$appointmentId)->where('provider_id',$provider->id)->firstOrFail();
        if ($appointment->status === 'cancelled') return response()->json(['message'=>'No puedes modificar una cita cancelada'], 422);

        $request->validate([
            'vehicle_id'     => 'nullable|exists:provider_vehicles,id',
            'vehicle_custom' => 'nullable|string|max:255',
            'personnel_id'   => 'nullable|exists:provider_personnel,id',
            'driver_custom'  => 'nullable|string|max:255',
            'provider_notes' => 'nullable|string|max:1000',
        ]);

        $appointment->update([
            'vehicle_id'               => !empty($request->vehicle_id)   ? (int)$request->vehicle_id   : null,
            'vehicle_custom'           => !empty($request->vehicle_custom) ? $request->vehicle_custom  : null,
            'personnel_id'             => !empty($request->personnel_id) ? (int)$request->personnel_id : null,
            'driver_custom'            => !empty($request->driver_custom)  ? $request->driver_custom   : null,
            'provider_notes'           => !empty($request->provider_notes) ? $request->provider_notes  : null,
            'completed_by_provider_at' => now(),
        ]);

        return response()->json(['message'=>'Información completada correctamente','appointment'=>$this->formatAppointment($appointment->fresh(['vehicle','personnel']))]);
    }

    // ── Seguridad ─────────────────────────────────────────────────────────────

   public function securityIndex(Request $request): JsonResponse
    {
        $query = Appointment::with([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'vehicle:id,brand_model,plates',
            'personnel:id,full_name',
            'entryConfirmedBy:id,name',
            'noShowRegisteredBy:id,name',
        ])->whereNotIn('status', ['cancelled']);
 
        // ── Filtro por vista ──────────────────────────────────────
        if ($request->filled('date')) {
            // Vista día
            $query->whereDate('appointment_date', $request->date);
 
        } elseif ($request->filled('week_start')) {
            // Vista semana
            $start = \Carbon\Carbon::parse($request->week_start);
            $end   = $start->copy()->addDays(6);
            $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
 
        } elseif ($request->filled('year') && $request->filled('month')) {
            // ✅ Vista mes — NUEVO
            $query->whereYear('appointment_date', $request->year)
                  ->whereMonth('appointment_date', $request->month);
 
        } else {
            // Default: hoy
            $query->whereDate('appointment_date', today());
        }
 
        $appointments = $query->orderBy('appointment_date')->orderBy('appointment_time')->get();
 
        return response()->json([
            'appointments' => $appointments->map(fn($a) => $this->formatAppointment($a)),
        ]);
    }

    public function getPhysicalDocsConfig($appointmentId): JsonResponse
    {
        $appointment = Appointment::with(['provider.providerType'])->findOrFail($appointmentId);
        $typeName    = $appointment->provider->providerType?->name ?? '';

        if (!$this->requiresPhysicalDocs($typeName) || $appointment->type !== 'entrega')
            return response()->json(['requires_docs'=>false,'docs'=>[]]);

        return response()->json([
            'requires_docs' => true,
            'provider_type' => $typeName,
            'docs'          => self::PHYSICAL_DOCS[$typeName] ?? [],
        ]);
    }

    public function confirmEntry(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::with(['provider.providerType'])->findOrFail($appointmentId);
        if ($appointment->status === 'cancelled')
            return response()->json(['message'=>'La cita está cancelada'], 422);

        $request->validate([
            'entry_notes'         => 'nullable|string|max:500',
            'actual_arrival_time' => 'required|date_format:H:i',
            'physical_docs'       => 'nullable|array',
            'physical_docs.*'     => 'boolean',
        ]);

        // Calcular puntualidad
        $scheduled     = Carbon::parse($appointment->appointment_date->format('Y-m-d').' '.$appointment->appointment_time);
        $actual        = Carbon::parse($appointment->appointment_date->format('Y-m-d').' '.$request->actual_arrival_time);
        $delayMinutes  = (int) max(0, $scheduled->diffInMinutes($actual, false));
        $arrivedOnTime = $delayMinutes <= 0;

        // Procesar checklist de documentos físicos
        $physicalDocsStatus = null;
        $hasMissingDocs     = false;
        $typeName           = $appointment->provider->providerType?->name ?? '';

        if ($this->requiresPhysicalDocs($typeName) && $appointment->type === 'entrega') {
            $docsConfig = self::PHYSICAL_DOCS[$typeName] ?? [];
            $submitted  = $request->physical_docs ?? [];

            $physicalDocsStatus = collect($docsConfig)->map(function($doc) use ($submitted) {
                $present = isset($submitted[$doc['key']]) && (bool)$submitted[$doc['key']];
                return [
                    'key'      => $doc['key'],
                    'label'    => $doc['label'],
                    'required' => $doc['required'],
                    'present'  => $present,
                    'missing'  => !$present,
                ];
            })->values()->toArray();

            $hasMissingDocs = collect($physicalDocsStatus)
                ->where('required', true)->where('present', false)->isNotEmpty();
        }

        $appointment->update([
        'entry_confirmed_at'   => now(),
        'entry_confirmed_by'   => auth()->id(),
        'entry_notes'          => $request->entry_notes ?? null,
        'actual_arrival_time'  => $request->actual_arrival_time,
        'arrived_on_time'      => $arrivedOnTime,
        'delay_minutes'        => $delayMinutes > 0 ? $delayMinutes : null,
        'physical_docs_status' => $physicalDocsStatus ?: null,
        'has_missing_docs'     => $hasMissingDocs,
        'status'               => 'confirmed',
    ]);
    
    // ✅ NUEVO: notificar que el proveedor llegó
    NotificationDispatcher::notifyRoles(
        ['ingeniero_alimentos', 'compras', 'admin', 'super_admin'],
        auth()->id(),
        'appointment_entry_confirmed',
        'Proveedor llegó',
        "{$appointment->provider->business_name} llegó a su cita de las " .
            substr($appointment->appointment_time, 0, 5) . " hrs" .
            ($hasMissingDocs ? ' (con documentos faltantes)' : ''),
        ['appointment_id' => $appointment->id, 'link' => '/food-engineer']
    );
    
    return response()->json([
        'message'          => 'Entrada confirmada correctamente',
        'arrived_on_time'  => $arrivedOnTime,
        'delay_minutes'    => $delayMinutes > 0 ? $delayMinutes : null,
        'has_missing_docs' => $hasMissingDocs,
        'appointment'      => $this->formatAppointment($appointment->fresh(['provider.providerType','vehicle','personnel','entryConfirmedBy'])),
    ]);

    }

    // ── Ingeniero de Alimentos ────────────────────────────────────────────────

           public function foodEngineerIndex(Request $request): JsonResponse
    {
        $with = [
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'receptionReviewedBy:id,name',
            'items.productService:id,name,type',
            'items.unit:id,name,abbreviation',
            'items.receivedUnit:id,name,abbreviation',
        ];
 
        // ── Vista mes ─────────────────────────────────────────────
        if ($request->filled('year') && $request->filled('month')) {
            $deliveries = Appointment::with($with)
                ->deliveries()
                ->whereYear('appointment_date', $request->year)
                ->whereMonth('appointment_date', $request->month)
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->get();
 
            return response()->json([
                'month_deliveries' => $deliveries->map(fn($a) => $this->formatAppointment($a)),
            ]);
        }
 
        // ── Vista semana ──────────────────────────────────────────
        if ($request->filled('week_start')) {
            $start = \Carbon\Carbon::parse($request->week_start);
            $end   = $start->copy()->addDays(6);
 
            $deliveries = Appointment::with($with)
                ->deliveries()
                ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->get();
 
            return response()->json([
                'today' => $deliveries->map(fn($a) => $this->formatAppointment($a)),
            ]);
        }
 
        // ── Vista día ─────────────────────────────────────────────
        if ($request->filled('date')) {
            $deliveries = Appointment::with($with)
                ->deliveries()
                ->whereDate('appointment_date', $request->date)
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('appointment_time')
                ->get();
 
            return response()->json([
                'today' => $deliveries->map(fn($a) => $this->formatAppointment($a)),
                'stats' => [
                    'today_total'    => $deliveries->count(),
                    'today_pending'  => $deliveries->where('reception_status', 'pending')->count(),
                    'today_accepted' => $deliveries->whereIn('reception_status', ['accepted'])->count(),
                    'today_rejected' => $deliveries->whereIn('reception_status', ['rejected', 'partial'])->count(),
                ],
            ]);
        }
 
        // ── Default: hoy + historial ──────────────────────────────
        $today = Appointment::with($with)
            ->forToday()->deliveries()
            ->orderBy('appointment_time')
            ->get();
 
        // Historial con filtros
        $historyQuery = Appointment::with($with)
            ->deliveries()
            ->whereNotNull('reception_reviewed_at');
 
        if ($request->filled('provider_id')) $historyQuery->where('provider_id', $request->provider_id);
        if ($request->filled('date_from'))   $historyQuery->whereDate('appointment_date', '>=', $request->date_from);
        else                                  $historyQuery->whereDate('appointment_date', '>=', today()->subDays(90));
        if ($request->filled('date_to'))     $historyQuery->whereDate('appointment_date', '<=', $request->date_to);
 
        $history = $historyQuery
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(100)->get();
 
        $providerIds = Appointment::deliveries()
            ->whereNotNull('reception_reviewed_at')
            ->distinct()->pluck('provider_id');
        $providers = \App\Models\Provider::whereIn('id', $providerIds)
            ->select('id','business_name')->orderBy('business_name')->get();
 
        return response()->json([
            'today'     => $today->map(fn($a) => $this->formatAppointment($a)),
            'history'   => $history->map(fn($a) => $this->formatAppointment($a)),
            'providers' => $providers,
            'stats'     => [
                'today_total'    => $today->count(),
                'today_pending'  => $today->where('reception_status', 'pending')->count(),
                'today_accepted' => $today->whereIn('reception_status', ['accepted'])->count(),
                'today_rejected' => $today->whereIn('reception_status', ['rejected', 'partial'])->count(),
            ],
        ]);
    }

    public function registerReception(Request $request, $appointmentId): JsonResponse
    {
        $appointment = \App\Models\Appointment::with('items')->findOrFail($appointmentId);

        if ($appointment->type !== 'entrega') {
            return response()->json(['message' => 'Solo se pueden registrar recepciones para entregas'], 422);
        }

        // ✅ Normalizar antes de validar: not_delivered a bool real, y limpiar
        // cantidad/unidad cuando el ítem viene marcado como no entregado, para
        // evitar el problema de casting de PostgreSQL con strings vacíos.
        $items = collect($request->input('items', []))->map(function ($item) {
            $item['not_delivered'] = filter_var($item['not_delivered'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($item['not_delivered']) {
                $item['quantity_received'] = null;
                $item['received_unit_id']  = null;
                $item['quantity_rejected'] = null;
                $item['rejection_reason']  = null;
            }
            return $item;
        })->toArray();
        $request->merge(['items' => $items]);

        $request->validate([
            'reception_notes' => 'nullable|string|max:2000',
            'items'           => 'required|array|min:1',
            'items.*.id'      => 'required|exists:appointment_items,id',
            'items.*.not_delivered'      => 'nullable|boolean',
            'items.*.quantity_received'  => 'required_if:items.*.not_delivered,false|nullable|numeric|min:0',
            'items.*.received_unit_id'   => 'required_if:items.*.not_delivered,false|nullable|exists:units,id',
            'items.*.quantity_rejected'  => 'nullable|numeric|min:0',
            'items.*.rejection_reason'   => 'nullable|in:inocuidad,calidad',
            'items.*.reception_notes'    => 'nullable|string|max:500',
        ], [
            'items.required'                        => 'Debes registrar al menos un ítem',
            'items.*.quantity_received.required_if' => 'La cantidad recibida es requerida',
            'items.*.received_unit_id.required_if'  => 'La unidad es requerida',
        ]);

        foreach ($request->items as $itemData) {
            $item = AppointmentItem::find($itemData['id']);
            if (!$item || $item->appointment_id !== $appointment->id) continue;

            $notDelivered = filter_var($itemData['not_delivered'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // ✅ Ítem no entregado: se guarda como estado propio, sin cantidades
            if ($notDelivered) {
                $item->update([
                    'not_delivered'      => true,
                    'quantity_received'  => null,
                    'quantity_rejected'  => null,
                    'received_unit_id'   => null,
                    'reception_status'   => 'not_delivered',
                    'rejection_reason'   => null,
                    'reception_notes'    => $itemData['reception_notes'] ?? null,
                ]);
                continue;
            }

            $qtyRec = (float) $itemData['quantity_received'];
            $qtyRej = (float) ($itemData['quantity_rejected'] ?? 0);
 
            // Determinar estado del ítem
            if ($qtyRej >= $qtyRec && $qtyRec > 0) {
                $status = 'rejected';
            } elseif ($qtyRej > 0 && $qtyRej < $qtyRec) {
                $status = 'partial';
            } else {
                $status = 'accepted';
            }
 
            $item->update([
                'not_delivered'      => false,
                'quantity_received'  => $qtyRec,
                'quantity_rejected'  => $qtyRej > 0 ? $qtyRej : null,
                'received_unit_id'   => $itemData['received_unit_id'],
                'reception_status'   => $status,
                'rejection_reason'   => $itemData['rejection_reason'] ?? null,
                'reception_notes'    => $itemData['reception_notes'] ?? null,
            ]);
        }
 
        // Estado global de la recepción basado en los ítems
        $appointment->refresh();
        $allStatuses = $appointment->items->pluck('reception_status');

        // ✅ Los ítems "no entregados" se excluyen del cálculo de aceptado/rechazado total,
        // pero si TODOS los ítems no llegaron, el estado global también es 'not_delivered'.
        $deliveredStatuses = $allStatuses->reject(fn($s) => $s === 'not_delivered');

        if ($deliveredStatuses->isEmpty()) {
            $globalStatus = 'not_delivered';
        } else {
            $hasNotDelivered = $allStatuses->contains('not_delivered');
            $allAccepted     = $deliveredStatuses->every(fn($s) => $s === 'accepted');
            $allRejected      = $deliveredStatuses->every(fn($s) => $s === 'rejected');

            $globalStatus = match(true) {
                $allAccepted && !$hasNotDelivered => 'accepted',
                $allRejected && !$hasNotDelivered => 'rejected',
                default                            => 'partial',
            };
        }
 
        $appointment->update([
            'reception_status'      => $globalStatus,
            'reception_notes'       => $request->reception_notes ?? null,
            'reception_reviewed_by' => auth()->id(),
            'reception_reviewed_at' => now(),
            'status'                => in_array($appointment->status, ['confirmed','scheduled']) ? 'completed' : $appointment->status,
        ]);
 
        return response()->json([
            'message'     => 'Recepción registrada correctamente',
            'appointment' => $this->formatAppointment(
                $appointment->fresh(['provider', 'items.productService', 'items.unit', 'items.receivedUnit', 'receptionReviewedBy'])
            ),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requiresPhysicalDocs(string $typeName): bool
    {
        return in_array($typeName, self::PROVIDER_TYPES_WITH_DOCS);
    }

        protected function formatAppointment(Appointment $a): array
    {
        return [
            'id'                       => $a->id,
            'provider'                 => $a->provider ? ['id'=>$a->provider->id,'business_name'=>$a->provider->business_name,'rfc'=>$a->provider->rfc,'provider_type_id'=>$a->provider->provider_type_id] : null,
            'appointment_date'         => $a->appointment_date?->format('Y-m-d'),
            'appointment_time'         => $a->appointment_time,
            'duration_minutes'         => $a->duration_minutes ?? 60,
            'end_time'                 => $a->end_time,
            'type'                     => $a->type,
            'type_label'               => $a->type_label,
            'status'                   => $a->status,
            'status_label'             => $a->status_label,
            'notes'                    => $a->notes,
            'products'                 => $a->products, // legacy
            'has_attachment'           => (bool)$a->attachment_path,
            'attachment_name'          => $a->attachment_name,
            // Proveedor
            'vehicle_display'          => $a->vehicle_display,
            'driver_display'           => $a->driver_display,
            'is_completed_by_provider' => $a->is_completed_by_provider,
            'provider_notes'           => $a->provider_notes,
            // Seguridad
            'is_entry_confirmed'       => $a->is_entry_confirmed,
            'entry_confirmed_at'       => $a->entry_confirmed_at?->format('H:i'),
            'entry_notes'              => $a->entry_notes,
            'actual_arrival_time'      => $a->actual_arrival_time,
            'arrived_on_time'          => $a->arrived_on_time,
            'delay_minutes'            => $a->delay_minutes,
            'has_missing_docs'         => $a->has_missing_docs,
            'physical_docs_status'     => $a->physical_docs_status,
            // No show
            'is_no_show'               => $a->is_no_show,
            'no_show_at'               => $a->no_show_at?->format('H:i'),
            'no_show_notes'            => $a->no_show_notes,
            // ✅ Reagendado
            'rescheduled_from_id'      => $a->rescheduled_from_id,
            'rescheduled_to_id'        => $a->rescheduled_to_id,
            'is_rescheduled'           => (bool) $a->rescheduled_to_id,
            'rescheduled_from'         => $a->relationLoaded('rescheduledFrom') && $a->rescheduledFrom ? [
                'id'               => $a->rescheduledFrom->id,
                'appointment_date' => $a->rescheduledFrom->appointment_date?->format('Y-m-d'),
                'appointment_time' => $a->rescheduledFrom->appointment_time,
            ] : null,
            // Recepción (legacy — un solo producto)
            'reception_status'         => $a->reception_status ?? 'pending',
            'reception_label'          => $a->reception_label,
            'reception_notes'          => $a->reception_notes,
            'reception_reviewed_by'    => $a->receptionReviewedBy?->name,
            'reception_reviewed_at'    => $a->reception_reviewed_at?->format('Y-m-d H:i'),
            'is_reception_reviewed'    => $a->is_reception_reviewed,
            'quantity_received'        => $a->quantity_received,
            'quantity_rejected'        => $a->quantity_rejected,
            'unit'                     => $a->unit ? ['id'=>$a->unit->id,'name'=>$a->unit->name,'abbreviation'=>$a->unit->abbreviation] : null,
            'rejection_reason'         => $a->rejection_reason,
            'is_partial_rejection'     => $a->is_partial_rejection,
            // ✅ ITEMS MÚLTIPLES
            'items'                    => $a->items ? $a->items->map(fn($item) => [
                'id'                    => $item->id,
                'product_service_id'    => $item->product_service_id,
                'product_name'          => $item->productService?->name,
                'product_type'          => $item->productService?->type,
                'quantity_expected'     => $item->quantity_expected,
                'unit'                  => $item->unit ? ['id'=>$item->unit->id,'abbreviation'=>$item->unit->abbreviation,'name'=>$item->unit->name] : null,
                'notes'                 => $item->notes,
                'not_delivered'         => (bool) $item->not_delivered,
                'quantity_received'     => $item->quantity_received,
                'quantity_rejected'     => $item->quantity_rejected,
                'quantity_accepted'     => $item->quantity_accepted,
                'received_unit'         => $item->receivedUnit ? ['id'=>$item->receivedUnit->id,'abbreviation'=>$item->receivedUnit->abbreviation] : null,
                'reception_status'      => $item->reception_status,
                'reception_status_label'=> $item->reception_status_label,
                'rejection_reason'      => $item->rejection_reason,
                'rejection_reason_label'=> $item->rejection_reason_label,
                'reception_notes'       => $item->reception_notes,
            ])->values() : [],
        ];
    }

    // ── MÉTODO: markNoShow (NUEVO) ────────────────────────────────────
    // POST /api/security/appointments/{id}/no-show
 
    public function markNoShow(Request $request, $appointmentId): JsonResponse
    {
        $appointment = \App\Models\Appointment::findOrFail($appointmentId);
 
        if (in_array($appointment->status, ['cancelled', 'completed', 'no_show'])) {
            return response()->json(['message' => 'Esta cita no puede marcarse como no presentado'], 422);
        }
 
        $request->validate([
            'no_show_notes' => 'nullable|string|max:500',
        ]);
 
        $appointment->update([
        'status'                => 'no_show',
        'no_show_at'            => now(),
        'no_show_registered_by' => auth()->id(),
        'no_show_notes'         => $request->no_show_notes ?? null,
    ]);
    
    // ✅ NUEVO: notificar que el proveedor no se presentó
    $appointment->load('provider:id,business_name');
    NotificationDispatcher::notifyRoles(
        ['compras', 'ingeniero_alimentos', 'admin', 'super_admin'],
        auth()->id(),
        'appointment_no_show',
        'Proveedor no se presentó',
        "{$appointment->provider->business_name} no llegó a su cita de las " .
            substr($appointment->appointment_time, 0, 5) . " hrs",
        ['appointment_id' => $appointment->id, 'link' => '/appointments']
    );
    
    return response()->json([
        'message'     => 'Registrado como no presentado',
        'appointment' => $this->formatAppointment($appointment->fresh(['provider'])),
    ]);

    }
    private function validateBusinessHours(string $date, string $time, callable $fail): void
    {
        $carbon    = Carbon::parse($date);
        [$h, $m]   = array_map('intval', explode(':', $time));
        $totalMins = $h * 60 + $m;

        if ($carbon->dayOfWeek === Carbon::SUNDAY) {
            $fail('No se agendan citas los domingos');
            return;
        }
        if ($carbon->dayOfWeek === Carbon::SATURDAY) {
            if ($totalMins < 8*60 || $totalMins > 14*60)
                $fail('Los sábados el horario es de 8:00 a 14:00');
            return;
        }
        if ($totalMins < 8*60 || $totalMins > 18*60)
            $fail('El horario de lunes a viernes es de 8:00 a 18:00');
    }

    private function notifyProvider(Appointment $appointment, string $action): void
    {
        try {
            $provider = $appointment->provider;
            if (!$provider) return;
            $providerUser = User::where('email', $provider->email)->first();
            if (!$providerUser) return;
            $subject = $action === 'scheduled' ? '📅 Nueva cita agendada — SGP DASAVENA' : '❌ Cita cancelada — SGP DASAVENA';
            Mail::send('emails.appointment-notification', [
                'providerName'  => $provider->business_name,
                'action'        => $action,
                'date'          => Carbon::parse($appointment->appointment_date)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
                'time'          => $appointment->appointment_time,
                'typeLabel'     => $appointment->type_label,
                'notes'         => $appointment->notes,
                'cancelReason'  => $appointment->cancellation_reason,
                'hasAttachment' => (bool)$appointment->attachment_path,
                'portalUrl'     => config('app.frontend_url').'/provider/appointments',
            ], function($message) use ($providerUser, $subject) {
                $message->to($providerUser->email, $providerUser->name)->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Error notificando cita al proveedor: '.$e->getMessage());
        }
    }

        public function getProviderProducts(Request $request, $providerId): JsonResponse
    {
        $provider = \App\Models\Provider::findOrFail($providerId);
 
        $items = $provider->productsServices()
            ->where('is_active', true)
            ->with('category:id,name,type')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn($item) => [
                'id'       => $item->id,
                'name'     => $item->name,
                'type'     => $item->type,
                'category' => $item->category?->name,
            ]);
 
        return response()->json([
            'products' => $items->where('type', 'product')->values(),
            'services' => $items->where('type', 'service')->values(),
        ]);
    }
}