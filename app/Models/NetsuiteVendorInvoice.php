<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetsuiteVendorInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'netsuite_internal_id',
        'tran_id',
        'tran_date',
        'due_date',
        'amount',
        'status',
        'currency',
        'memo',
        'netsuite_last_modified',
        'last_synced_at',
        'pdf_file_id',
    ];

    protected function casts(): array
    {
        return [
            'tran_date' => 'date',
            'due_date' => 'date',
            'netsuite_last_modified' => 'date',
            'last_synced_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function paymentApplications(): HasMany
    {
        return $this->hasMany(NetsuitePaymentApplication::class, 'invoice_id');
    }

    public function creditNoteRequests(): HasMany
    {
        return $this->hasMany(ProviderCreditNoteRequest::class, 'related_invoice_id');
    }
}