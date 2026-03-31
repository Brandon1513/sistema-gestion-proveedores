<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\Unit;

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
        $validated = $request->validate([
            'provider_id'      => 'required|exists:providers,id',
            'appointment_date' => ['required','date','after_or_equal:today'],
            'appointment_time' => ['required','date_format:H:i', function($attr,$value,$fail) use ($request) {
                $this->validateBusinessHours($request->appointment_date, $value, $fail);
            }],
            'type'       => 'required|in:entrega,residuos,auditoria,calibracion,servicio',
            'products'   => 'nullable|string|max:1000',
            'notes'      => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $attachmentPath = null; $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('appointments','documents');
            $attachmentName = $file->getClientOriginalName();
        }

        $appointment = Appointment::create([
            'provider_id'      => $validated['provider_id'],
            'scheduled_by'     => auth()->id(),
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'type'             => $validated['type'],
            'products'         => $validated['products'] ?? null,
            'notes'            => $validated['notes']    ?? null,
            'attachment_path'  => $attachmentPath,
            'attachment_name'  => $attachmentName,
            'status'           => 'scheduled',
            'reception_status' => 'pending',
        ]);

        $appointment->load(['provider:id,business_name,rfc,email','scheduledBy:id,name']);
        $this->notifyProvider($appointment, 'scheduled');
        return response()->json(['message'=>'Cita agendada correctamente','appointment'=>$this->formatAppointment($appointment)], 201);
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
        ]);
        return response()->json(['appointment' => $this->formatAppointment($appointment)]);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
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
        return response()->json(['message'=>'Cita actualizada','appointment'=>$this->formatAppointment($appointment->fresh(['provider','scheduledBy','vehicle','personnel']))]);
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
            'vehicle:id,brand_model,plates,color',
            'personnel:id,full_name,position',
            'entryConfirmedBy:id,name',
        ]);

        if ($request->filled('date')) {
            $query->forDate($request->date);
        } elseif ($request->view === 'week' && $request->filled('week_start')) {
            $weekEnd = Carbon::parse($request->week_start)->addDays(6)->format('Y-m-d');
            $query->whereBetween('appointment_date', [$request->week_start, $weekEnd]);
        } else {
            $query->forToday();
        }

        $query->whereNotIn('status',['cancelled']);
        $appointments = $query->orderBy('appointment_time')->get();

        return response()->json([
            'appointments' => $appointments->map(fn($a) => $this->formatAppointment($a)),
            'total'        => $appointments->count(),
            'date'         => $request->date ?? today()->format('Y-m-d'),
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
            'physical_docs_status' => $physicalDocsStatus ? json_encode($physicalDocsStatus) : null,
            'has_missing_docs'     => $hasMissingDocs,
            'status'               => 'confirmed',
        ]);

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
        // Entregas de hoy
        $today = Appointment::with([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'vehicle:id,brand_model,plates',
            'personnel:id,full_name',
            'entryConfirmedBy:id,name',
            'receptionReviewedBy:id,name',
            'unit:id,name,abbreviation',
        ])->forToday()->deliveries()->orderBy('appointment_time')->get();
 
        // Historial con filtros
        $historyQuery = Appointment::with([
            'provider:id,business_name,rfc',
            'receptionReviewedBy:id,name',
            'unit:id,name,abbreviation',
        ])->deliveries()->whereNotNull('reception_reviewed_at');
 
        // Filtro por proveedor
        if ($request->filled('provider_id')) {
            $historyQuery->where('provider_id', $request->provider_id);
        }
 
        // Filtro por fecha inicio
        if ($request->filled('date_from')) {
            $historyQuery->whereDate('appointment_date', '>=', $request->date_from);
        } else {
            // Default: últimos 90 días
            $historyQuery->whereDate('appointment_date', '>=', today()->subDays(90));
        }
 
        // Filtro por fecha fin
        if ($request->filled('date_to')) {
            $historyQuery->whereDate('appointment_date', '<=', $request->date_to);
        }
 
        $history = $historyQuery->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(100)
            ->get();
 
        // Lista de proveedores para el filtro (solo los que tienen entregas)
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
                'today_pending'  => $today->where('reception_status','pending')->count(),
                'today_accepted' => $today->where('reception_status','accepted')->count(),
                'today_rejected' => $today->where('reception_status','rejected')->count(),
            ],
        ]);
    }

        public function registerReception(Request $request, $appointmentId): JsonResponse
    {
        $appointment = Appointment::findOrFail($appointmentId);
 
        if ($appointment->type !== 'entrega') {
            return response()->json(['message'=>'Solo se pueden registrar recepciones para entregas'], 422);
        }
 
        $request->validate([
            'reception_status'  => 'required|in:accepted,rejected',
            'reception_notes'   => 'nullable|string|max:2000',
            'quantity_received' => 'required|numeric|min:0.01',
            'unit_id'           => 'required|exists:units,id',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'rejection_reason'  => 'nullable|in:inocuidad,calidad',
            'photos'            => 'nullable|array|max:5',
            'photos.*'          => 'file|image|max:5120',
        ], [
            'quantity_received.required' => 'La cantidad recibida es requerida',
            'quantity_received.min'      => 'La cantidad debe ser mayor a 0',
            'unit_id.required'           => 'Selecciona una unidad de medida',
            'rejection_reason.in'        => 'El motivo debe ser inocuidad o calidad',
        ]);
 
        // Si hay rechazo, validar que haya motivo y cantidad rechazada
        if ($request->reception_status === 'rejected') {
            if (!$request->rejection_reason) {
                return response()->json([
                    'message' => 'Debes indicar el motivo del rechazo',
                    'errors'  => ['rejection_reason' => ['El motivo de rechazo es requerido']],
                ], 422);
            }
            if (!$request->reception_notes) {
                return response()->json([
                    'message' => 'Las observaciones son obligatorias al rechazar',
                    'errors'  => ['reception_notes' => ['Debes indicar observaciones']],
                ], 422);
            }
        }
 
        $quantityReceived = (float) $request->quantity_received;
        $quantityRejected = $request->filled('quantity_rejected') ? (float) $request->quantity_rejected : 0;
 
        // Validar que rechazado no supere recibido
        if ($quantityRejected > $quantityReceived) {
            return response()->json([
                'message' => 'La cantidad rechazada no puede ser mayor a la recibida',
                'errors'  => ['quantity_rejected' => ['No puede superar la cantidad recibida']],
            ], 422);
        }
 
        // Rechazo parcial: llegó algo y se rechazó algo
        $isPartialRejection = $quantityRejected > 0 && $quantityRejected < $quantityReceived;
 
        // Estado final: si hay rechazo parcial → accepted (recibió algo)
        //               si rechazó todo        → rejected
        //               si aceptó todo         → accepted
        $finalReceptionStatus = $request->reception_status;
        if ($isPartialRejection) {
            $finalReceptionStatus = 'accepted'; // aceptado aunque hubo devolución parcial
        }
 
        // Estado de la cita
        $finalAppointmentStatus = ($finalReceptionStatus === 'accepted' && $appointment->status === 'confirmed')
            ? 'completed'
            : $appointment->status;
 
        // Fotos
        $photoPaths = $appointment->reception_photos ?? [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store("appointments/{$appointment->id}/reception", 'documents');
            }
        }
 
        $appointment->update([
            'reception_status'      => $finalReceptionStatus,
            'reception_notes'       => $request->reception_notes ?? null,
            'reception_photos'      => $photoPaths,
            'reception_reviewed_by' => auth()->id(),
            'reception_reviewed_at' => now(),
            'quantity_received'     => $quantityReceived,
            'quantity_rejected'     => $quantityRejected > 0 ? $quantityRejected : null,
            'unit_id'               => $request->unit_id,
            'rejection_reason'      => $request->rejection_reason ?? null,
            'is_partial_rejection'  => $isPartialRejection,
            'status'                => $finalAppointmentStatus,
        ]);
 
        $message = $isPartialRejection
            ? "Recepción parcial registrada. Se devuelven {$quantityRejected} {$appointment->unit?->abbreviation} al proveedor."
            : ($finalReceptionStatus === 'accepted'
                ? 'Producto aceptado correctamente'
                : 'Producto rechazado en su totalidad.');
 
        return response()->json([
            'message'     => $message,
            'appointment' => $this->formatAppointment($appointment->fresh(['provider','receptionReviewedBy','unit'])),
        ]);
    }
 

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requiresPhysicalDocs(string $typeName): bool
    {
        return in_array($typeName, self::PROVIDER_TYPES_WITH_DOCS);
    }

    private function formatAppointment(Appointment $a): array
    {
        $physicalDocs = null;
        if ($a->physical_docs_status) {
            $physicalDocs = is_string($a->physical_docs_status)
                ? json_decode($a->physical_docs_status, true)
                : $a->physical_docs_status;
        }

        return [
            'id'                       => $a->id,
            'provider'                 => $a->provider ? [
                'id'            => $a->provider->id,
                'business_name' => $a->provider->business_name,
                'rfc'           => $a->provider->rfc,
                'type'          => $a->provider->providerType?->name,
                'type_id'       => $a->provider->provider_type_id,
            ] : null,
            'scheduled_by'             => $a->scheduledBy?->name,
            'appointment_date'         => $a->appointment_date?->format('Y-m-d'),
            'appointment_time'         => $a->appointment_time,
            'type'                     => $a->type,
            'type_label'               => $a->type_label,
            'vehicle_id'               => $a->vehicle_id,
            'vehicle'                  => $a->vehicle ? ['id'=>$a->vehicle->id,'brand_model'=>$a->vehicle->brand_model,'plates'=>$a->vehicle->plates] : null,
            'vehicle_custom'           => $a->vehicle_custom,
            'vehicle_display'          => $a->vehicle_display,
            'personnel_id'             => $a->personnel_id,
            'personnel'                => $a->personnel ? ['id'=>$a->personnel->id,'full_name'=>$a->personnel->full_name,'position'=>$a->personnel->position] : null,
            'driver_custom'            => $a->driver_custom,
            'driver_display'           => $a->driver_display,
            'provider_notes'           => $a->provider_notes,
            'is_completed_by_provider' => $a->is_completed_by_provider,
            'completed_by_provider_at' => $a->completed_by_provider_at?->format('Y-m-d H:i'),
            'products'                 => $a->products,
            'notes'                    => $a->notes,
            'has_attachment'           => (bool)$a->attachment_path,
            'attachment_name'          => $a->attachment_name,
            'status'                   => $a->status,
            'status_label'             => $a->status_label,
            'cancellation_reason'      => $a->cancellation_reason,
            'cancelled_by'             => $a->cancelledBy?->name,
            'cancelled_at'             => $a->cancelled_at?->format('Y-m-d H:i'),
            'is_entry_confirmed'       => $a->is_entry_confirmed,
            'entry_confirmed_at'       => $a->entry_confirmed_at?->format('H:i'),
            'entry_confirmed_by'       => $a->entryConfirmedBy?->name,
            'entry_notes'              => $a->entry_notes,
            'actual_arrival_time'      => $a->actual_arrival_time,
            'arrived_on_time'          => $a->arrived_on_time,
            'delay_minutes'            => $a->delay_minutes,
            'physical_docs_status'     => $physicalDocs,
            'has_missing_docs'         => (bool)$a->has_missing_docs,
            'reception_status'         => $a->reception_status ?? 'pending',
            'reception_label'          => $a->reception_label,
            'reception_notes'          => $a->reception_notes,
            'reception_photos'         => $a->reception_photos ?? [],
            'is_reception_reviewed'    => $a->is_reception_reviewed,
            'reception_reviewed_by'    => $a->receptionReviewedBy?->name,
            'reception_reviewed_at'    => $a->reception_reviewed_at?->format('Y-m-d H:i'),
            'quantity_received'        => $a->quantity_received,
            'quantity_rejected'        => $a->quantity_rejected,
            'unit'                     => $a->unit ? ['id'=>$a->unit->id,'name'=>$a->unit->name,'abbreviation'             =>$a->unit->abbreviation] : null,
            'rejection_reason'         => $a->rejection_reason,
            'rejection_reason_label'   => $a->rejection_reason_label,
            'is_partial_rejection'     => $a->is_partial_rejection,
            'created_at'               => $a->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function validateBusinessHours(string $date, string $time, callable $fail): void
    {
        $carbon = Carbon::parse($date);
        $hour   = (int) explode(':', $time)[0];
        if ($carbon->dayOfWeek === Carbon::SUNDAY) { $fail('No se agendan citas los domingos'); return; }
        if ($carbon->dayOfWeek === Carbon::SATURDAY) {
            if ($hour < 8 || $hour >= 14) $fail('Los sábados el horario es de 8:00 a 14:00');
            return;
        }
        if ($hour < 8 || $hour >= 18) $fail('El horario de lunes a viernes es de 8:00 a 18:00');
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
}