<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProviderType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'form_code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'document_type_provider_type')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProviderInvitation::class);
    }
}