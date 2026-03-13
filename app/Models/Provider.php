<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_type_id',
        'business_name',
        'rfc',
        'legal_representative',
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'city',
        'state',
        'postal_code',
        'phone',
        'email',
        'bank',
        'bank_branch',
        'account_number',
        'clabe',
        'credit_amount',
        'credit_days',
        'products',
        'services',
        'status',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'credit_amount' => 'decimal:2',
        'credit_days' => 'integer',
    ];

    // Relaciones
    public function providerType(): BelongsTo
    {
        return $this->belongsTo(ProviderType::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ProviderContact::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(ProviderVehicle::class);
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(ProviderPersonnel::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(ProviderCertification::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Accessors
    public function getFullAddressAttribute(): string
    {
        return "{$this->street} {$this->exterior_number}, {$this->neighborhood}, {$this->city}, {$this->state} {$this->postal_code}";
    }

    // Métodos de utilidad
    public function hasExpiredDocuments(): bool
    {
        return $this->documents()
            ->where('status', 'expired')
            ->exists();
    }

    public function hasPendingDocuments(): bool
    {
        return $this->documents()
            ->where('status', 'pending')
            ->exists();
    }
}