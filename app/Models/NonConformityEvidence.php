<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evidencia de una acción correctiva (Sección 3 del F-CAL-24) */
class NonConformityEvidence extends Model
{
    protected $table = 'non_conformity_evidences';

    const REVIEW_PENDING  = 'pending';
    const REVIEW_ACCEPTED = 'accepted';
    const REVIEW_REJECTED = 'rejected';

    // Mismos términos que el formato: Cumple / No cumple
    const REVIEW_LABELS = [
        self::REVIEW_PENDING  => 'En revisión',
        self::REVIEW_ACCEPTED => 'Cumple',
        self::REVIEW_REJECTED => 'No cumple',
    ];

    protected $fillable = [
        'non_conformity_action_id', 'attempt', 'path', 'original_name', 'file_extension', 'size_kb',
        'provider_comment', 'submitted_at', 'submitted_by',
        'review_status', 'review_comment', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'attempt'      => 'integer',
        'size_kb'      => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function action(): BelongsTo     { return $this->belongsTo(NonConformityAction::class, 'non_conformity_action_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function getReviewLabelAttribute(): string
    {
        return self::REVIEW_LABELS[$this->review_status] ?? $this->review_status;
    }
}