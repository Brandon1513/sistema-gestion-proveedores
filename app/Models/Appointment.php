<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_id','scheduled_by',
        'appointment_date','appointment_time','type',
        'vehicle_id','vehicle_custom','personnel_id','driver_custom',
        'provider_notes','completed_by_provider_at',
        'products','notes','attachment_path','attachment_name',
        'status','cancellation_reason','cancelled_by','cancelled_at',
        // Seguridad
        'entry_confirmed_at','entry_confirmed_by','entry_notes',
        'actual_arrival_time','arrived_on_time','delay_minutes',
        'physical_docs_status','has_missing_docs',
        // Ingeniero
        'reception_status','reception_notes','reception_photos',
        'reception_reviewed_by','reception_reviewed_at',
        'quantity_received','quantity_rejected','unit_id',
        'rejection_reason','is_partial_rejection',
    ];

    protected $casts = [
        'appointment_date'         => 'date',
        'cancelled_at'             => 'datetime',
        'completed_by_provider_at' => 'datetime',
        'entry_confirmed_at'       => 'datetime',
        'reception_reviewed_at'    => 'datetime',
        'reception_photos'         => 'array',
        'arrived_on_time'          => 'boolean',
        'has_missing_docs'         => 'boolean',
        'is_partial_rejection'     => 'boolean',
        'quantity_received'        => 'decimal:2',
        'quantity_rejected'        => 'decimal:2',
    ];

    const TYPE_LABELS      = ['entrega'=>'Entrega de mercancía','residuos'=>'Recolección de residuos','auditoria'=>'Auditoría / Calidad','calibracion'=>'Calibración de equipos','servicio'=>'Servicio general'];
    const STATUS_LABELS    = ['scheduled'=>'Agendada','confirmed'=>'Confirmada','cancelled'=>'Cancelada','completed'=>'Completada'];
    const RECEPTION_LABELS = ['pending'=>'Pendiente revisión','accepted'=>'Producto aceptado','rejected'=>'Producto rechazado'];
    const REJECTION_REASONS = ['inocuidad'=>'Inocuidad','calidad'=>'Calidad'];

    public function getTypeLabelAttribute(): string        { return self::TYPE_LABELS[$this->type] ?? $this->type; }
    public function getStatusLabelAttribute(): string      { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getReceptionLabelAttribute(): string   { return self::RECEPTION_LABELS[$this->reception_status ?? 'pending'] ?? 'Pendiente'; }
    public function getRejectionReasonLabelAttribute(): ?string { return self::REJECTION_REASONS[$this->rejection_reason] ?? null; }
    public function getVehicleDisplayAttribute(): ?string  { return $this->vehicle ? "{$this->vehicle->brand_model} — {$this->vehicle->plates}" : $this->vehicle_custom; }
    public function getDriverDisplayAttribute(): ?string   { return $this->personnel ? $this->personnel->full_name : $this->driver_custom; }
    public function getIsCompletedByProviderAttribute(): bool  { return $this->completed_by_provider_at !== null; }
    public function getIsEntryConfirmedAttribute(): bool       { return $this->entry_confirmed_at !== null; }
    public function getIsReceptionReviewedAttribute(): bool    { return $this->reception_reviewed_at !== null; }

    public function provider(): BelongsTo            { return $this->belongsTo(Provider::class); }
    public function scheduledBy(): BelongsTo         { return $this->belongsTo(User::class, 'scheduled_by'); }
    public function vehicle(): BelongsTo             { return $this->belongsTo(ProviderVehicle::class, 'vehicle_id'); }
    public function personnel(): BelongsTo           { return $this->belongsTo(ProviderPersonnel::class, 'personnel_id'); }
    public function cancelledBy(): BelongsTo         { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function entryConfirmedBy(): BelongsTo    { return $this->belongsTo(User::class, 'entry_confirmed_by'); }
    public function receptionReviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reception_reviewed_by'); }
    public function unit(): BelongsTo                { return $this->belongsTo(Unit::class); }

    public function scopeForToday($query)                        { return $query->whereDate('appointment_date', today())->whereNotIn('status',['cancelled']); }
    public function scopeForMonth($query, int $year, int $month) { return $query->whereYear('appointment_date',$year)->whereMonth('appointment_date',$month); }
    public function scopeForDate($query, string $date)           { return $query->whereDate('appointment_date',$date); }
    public function scopeDeliveries($query)                      { return $query->where('type','entrega'); }
}