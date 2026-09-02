<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCreditNoteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'related_invoice_id',
        'type',
        'description',
        'amount_requested',
        'file_path',
        'provider_response_file_path',
        'provider_responded_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'provider_responded_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(NetsuiteVendorInvoice::class, 'related_invoice_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}