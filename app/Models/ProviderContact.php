<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'type',
        'name',
        'email',
        'phone',
        'extension',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // Relaciones
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    // Scopes
    public function scopeSales($query)
    {
        return $query->where('type', 'sales');
    }

    public function scopeBilling($query)
    {
        return $query->where('type', 'billing');
    }

    public function scopeQuality($query)
    {
        return $query->where('type', 'quality');
    }
}