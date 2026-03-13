<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_document_id',
        'validated_by',
        'action',
        'comments',
        'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    // Relaciones
    public function providerDocument(): BelongsTo
    {
        return $this->belongsTo(ProviderDocument::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('action', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('action', 'rejected');
    }
}