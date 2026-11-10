<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sección 2 del F-CAL-24: análisis de causa raíz y acciones correctivas del proveedor.
 * Cada envío es una "vuelta"; el ingeniero la acepta o la regresa con comentario.
 */
class NonConformityResponse extends Model
{
    const REVIEW_PENDING  = 'pending';
    const REVIEW_ACCEPTED = 'accepted';
    const REVIEW_RETURNED = 'returned';

    const REVIEW_LABELS = [
        self::REVIEW_PENDING  => 'En revisión',
        self::REVIEW_ACCEPTED => 'Aceptada',
        self::REVIEW_RETURNED => 'Regresada',
    ];

    protected $fillable = [
        'non_conformity_id', 'round', 'team_leader', 'area', 'root_cause',
        'submitted_at', 'submitted_by',
        'review_status', 'review_comment', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'round'        => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function nonConformity(): BelongsTo { return $this->belongsTo(NonConformity::class); }
    public function actions(): HasMany         { return $this->hasMany(NonConformityAction::class)->orderBy('sort_order'); }
    public function submittedBy(): BelongsTo   { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewedBy(): BelongsTo    { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function getReviewLabelAttribute(): string
    {
        return self::REVIEW_LABELS[$this->review_status] ?? $this->review_status;
    }
}