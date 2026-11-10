<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonConformityPhoto extends Model
{
    protected $fillable = ['non_conformity_id', 'path', 'original_name', 'size_kb', 'uploaded_by'];

    protected $casts = ['size_kb' => 'integer'];

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(NonConformity::class);
    }
}