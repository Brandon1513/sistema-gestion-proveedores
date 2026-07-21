<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentItem extends Model
{
    protected $fillable = [
        'appointment_id',
        'product_service_id',
        'quantity_expected',
        'unit_id',
        'notes',
        'quantity_received',
        'quantity_rejected',
        'received_unit_id',
        'reception_status',
        'rejection_reason',
        'reception_notes',
        'not_delivered', 
    ];

    protected $casts = [
        'quantity_expected' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'quantity_rejected' => 'decimal:2',
        'not_delivered'     => 'boolean', 
    ];

    const REJECTION_REASONS = [
        'inocuidad' => 'Inocuidad',
        'calidad'   => 'Calidad',
    ];

    const RECEPTION_STATUSES = [
        'pending'       => 'Pendiente',
        'accepted'      => 'Aceptado',
        'rejected'      => 'Rechazado',
        'partial'       => 'Parcial',
        'not_delivered' => 'No entregado', 
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function receivedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'received_unit_id');
    }

    // Cantidad aceptada = recibida - rechazada
    public function getQuantityAcceptedAttribute(): float
    {
        if ($this->not_delivered) return 0; // ✅ nuevo: no hay cantidad aceptada si no llegó
        return max(0, (float)$this->quantity_received - (float)($this->quantity_rejected ?? 0));
    }

    public function getRejectionReasonLabelAttribute(): ?string
    {
        return self::REJECTION_REASONS[$this->rejection_reason] ?? null;
    }

    public function getReceptionStatusLabelAttribute(): string
    {
        return self::RECEPTION_STATUSES[$this->reception_status] ?? 'Pendiente';
    }
}