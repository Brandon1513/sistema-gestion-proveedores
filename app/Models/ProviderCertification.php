<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ProviderCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'certification_type',
        'other_name',
        'certification_number',
        'issue_date',
        'expiry_date',
        'certifying_body',
        // Validación
        'status',
        'validation_comments',
        'validated_by',
        'validated_at',
        // Archivo
        'file_path',
        'file_name',
        'file_size_kb',
        'file_extension',
    ];

    protected $casts = [
        'issue_date'   => 'date',
        'expiry_date'  => 'date',
        'validated_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) return false;
        return Carbon::now()->isAfter($this->expiry_date);
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) return null;
        return Carbon::now()->diffInDays($this->expiry_date, false);
    }

    // ✅ El proveedor puede editar/eliminar solo si está pending
    public function getIsEditableByProviderAttribute(): bool
    {
        return $this->status === 'pending';
    }
}