<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'brand_model',
        'color',
        'plates',
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