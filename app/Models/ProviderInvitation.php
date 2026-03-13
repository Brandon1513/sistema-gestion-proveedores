<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProviderInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'token',
        'provider_type_id',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at',
        'provider_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // Relaciones
    public function providerType(): BelongsTo
    {
        return $this->belongsTo(ProviderType::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere('expires_at', '<', Carbon::now());
    }

    // Accessors
    public function getIsExpiredAttribute(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    // Métodos estáticos
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    // Métodos de utilidad
    public function markAsAccepted(Provider $provider): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => Carbon::now(),
            'provider_id' => $provider->id,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }
}