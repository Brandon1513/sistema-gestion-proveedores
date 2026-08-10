<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'group_name',
        'sort_order',
        'is_active',
        'requires_expiry',
        'expiry_alert_days',
        'expiry_months',        // ✅ NUEVO
        'is_required',
        'allows_multiple',
        'allowed_extensions',
        'max_file_size_mb',
        'is_product_specific',
    ];

    protected $casts = [
        'requires_expiry'    => 'boolean',
        'is_required'        => 'boolean',
        'allows_multiple'    => 'boolean',
        'allowed_extensions' => 'array',
        'max_file_size_mb'   => 'integer',
        'expiry_alert_days'  => 'integer',
        'expiry_months'      => 'integer',  // ✅ NUEVO
        'is_active'          => 'boolean',
        'is_product_specific' => 'boolean',
    ];

    public function providerTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProviderType::class, 'document_type_provider_type')
            ->withPivot(['is_required', 'sort_order', 'applies_to_existing'])
            ->withTimestamps();
    }

    public function providerDocuments(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
    }

    public function isValidExtension(string $extension): bool
    {
        if (!$this->allowed_extensions) return true;
        return in_array(strtolower($extension), $this->allowed_extensions);
    }

    public function isValidFileSize(int $sizeInKb): bool
    {
        return $sizeInKb <= ($this->max_file_size_mb * 1024);
    }
}