<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class ProviderDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_id',
        'document_type_id',
        'file_path',
        'original_filename',
        'file_extension',
        'file_size_kb',
        'issue_date',
        'expiry_date',
        'status',
        'notes',
        'version',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'file_size_kb' => 'integer',
        'version' => 'integer',
    ];

    // Relaciones
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(DocumentValidation::class);
    }

    public function latestValidation(): HasMany
    {
        return $this->validations()->latest();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '<=', Carbon::now()->addDays($days))
            ->where('expiry_date', '>=', Carbon::now());
    }

    // Accessors
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return Carbon::now()->isAfter($this->expiry_date);
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return Carbon::now()->diffInDays($this->expiry_date, false);
    }

    public function getFileSizeMbAttribute(): float
    {
        return round($this->file_size_kb / 1024, 2);
    }

    // Métodos de utilidad
    public function needsExpiryAlert(): bool
    {
        if (!$this->expiry_date || !$this->documentType->expiry_alert_days) {
            return false;
        }

        $alertDate = Carbon::parse($this->expiry_date)
            ->subDays($this->documentType->expiry_alert_days);

        return Carbon::now()->isAfter($alertDate) && !$this->is_expired;
    }
}