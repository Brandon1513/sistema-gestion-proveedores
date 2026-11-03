<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentItemPhoto extends Model
{
    protected $fillable = [
        'appointment_item_id',
        'path',
        'original_name',
        'size_kb',
        'uploaded_by',
    ];

    protected $casts = [
        'size_kb' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AppointmentItem::class, 'appointment_item_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}