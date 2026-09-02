<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetsuiteCreditMemo extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'netsuite_internal_id',
        'tran_id',
        'tran_date',
        'amount',
        'amount_remaining',
        'memo',
        'netsuite_last_modified',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tran_date' => 'date',
            'netsuite_last_modified' => 'date',
            'last_synced_at' => 'datetime',
            'amount' => 'decimal:2',
            'amount_remaining' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}