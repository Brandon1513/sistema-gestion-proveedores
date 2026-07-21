<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarBlock extends Model
{
    protected $fillable = [
        'block_date',
        'start_time',
        'end_time',
        'title',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'block_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}