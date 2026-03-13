<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPersonnel extends Model
{
    use HasFactory;

     protected $table = 'provider_personnel';

    protected $fillable = [
        'provider_id',
        'full_name',
        'position',
        'identification_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}