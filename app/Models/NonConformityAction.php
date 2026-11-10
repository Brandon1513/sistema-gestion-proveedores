<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Acción correctiva de una vuelta de la Sección 2 (su evidencia se entrega en la Sección 3) */
class NonConformityAction extends Model
{
    protected $fillable = [
        'non_conformity_response_id', 'sort_order', 'action', 'responsible',
        'commitment_date', 'completion_date', 'evidence_to_deliver',
    ];

    protected $casts = [
        'sort_order'      => 'integer',
        'commitment_date' => 'date',
        'completion_date' => 'date',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(NonConformityResponse::class, 'non_conformity_response_id');
    }

    /** Intentos de evidencia, del más reciente al más antiguo */
    public function evidences(): HasMany
    {
        return $this->hasMany(NonConformityEvidence::class)->orderByDesc('attempt');
    }

    /** Estado de la evidencia de esta acción según su intento más reciente */
    public function getEvidenceStatusAttribute(): string
    {
        $latest = $this->relationLoaded('evidences') ? $this->evidences->first() : $this->evidences()->first();
        return $latest?->review_status ?? 'missing'; // missing | pending | accepted | rejected
    }

    /** El proveedor puede subir evidencia si no hay o si la última no cumplió */
    public function getCanUploadEvidenceAttribute(): bool
    {
        return in_array($this->evidence_status, ['missing', NonConformityEvidence::REVIEW_REJECTED], true);
    }
}