<?php

namespace App\Models;

use App\Models\ProviderRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function providerRequests(): HasMany
    {
        return $this->hasMany(ProviderRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}