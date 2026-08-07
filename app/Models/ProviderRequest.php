<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderRequest extends Model
{
    protected $fillable = [
        'requested_by',
        'department_id',
        'provider_business_name',
        'provider_contact_name',
        'provider_contact_phone',
        'provider_contact_email',
        'provider_type_id',
        'notes',
        'status',
        'provider_invitation_id',
        'provider_id',
        'processed_by',
        'processed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    const STATUS_LABELS = [
        'pending'    => 'Pendiente',
        'invited'    => 'Invitación enviada',
        'registered' => 'Proveedor registrado',
        'active'     => 'Proveedor activo',
        'rejected'   => 'Rechazada',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function providerType(): BelongsTo
    {
        return $this->belongsTo(ProviderType::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ProviderInvitation::class, 'provider_invitation_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}