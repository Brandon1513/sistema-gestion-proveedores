<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentChangeRequest extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ATTENDED = 'attended';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'provider_id',
        'document_type_id',
        'requested_by',
        'description',
        'status',
        'response_notes',
        'response_file_path',
        'response_original_filename',
        'attended_by',
        'attended_at',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    // La ruta interna del archivo nunca sale en JSON
    protected $hidden = ['response_file_path'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(DocumentChangeRequestImage::class, 'change_request_id')->orderBy('id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}