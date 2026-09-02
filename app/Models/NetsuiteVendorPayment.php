<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetsuiteVendorPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'netsuite_internal_id',
        'tran_id',
        'tran_date',
        'amount',
        'payment_method',
        'reference_number',
        'currency',
        'last_synced_at',
        'receipt_file_id',
    ];

    protected function casts(): array
    {
        return [
            'tran_date' => 'date',
            'last_synced_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(NetsuitePaymentApplication::class, 'payment_id');
    }
}