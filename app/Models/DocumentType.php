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
        'requires_expiry',
        'expiry_alert_days',
        'is_required',
        'allows_multiple',   // ← nuevo: permite múltiples cargas del mismo tipo
        'allowed_extensions',
        'max_file_size_mb',
    ];

    protected $casts = [
        'requires_expiry'    => 'boolean',
        'is_required'        => 'boolean',
        'allows_multiple'    => 'boolean',  // ← nuevo
        'allowed_extensions' => 'array',
        'max_file_size_mb'   => 'integer',
        'expiry_alert_days'  => 'integer',
    ];

    public function providerTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProviderType::class, 'document_type_provider_type')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    public function providerDocuments(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
    }

    public function isValidExtension(string $extension): bool
    {
        if (!$this->allowed_extensions) {
            return true;
        }
        return in_array(strtolower($extension), $this->allowed_extensions);
    }

    public function isValidFileSize(int $sizeInKb): bool
    {
        return $sizeInKb <= ($this->max_file_size_mb * 1024);
    }
}