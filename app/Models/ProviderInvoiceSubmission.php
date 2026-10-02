<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderInvoiceSubmission extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'provider_id', 'uploaded_by', 'uuid', 'serie', 'folio', 'issued_at', 'total',
        'currency', 'payment_method', 'issuer_rfc', 'receiver_rfc', 'pdf_path', 'xml_path',
        'status', 'netsuite_vendor_invoice_id', 'captured_at', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    // Las rutas internas de los archivos nunca se exponen al frontend.
    protected $hidden = ['pdf_path', 'xml_path'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'captured_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function netsuiteInvoice(): BelongsTo
    {
        return $this->belongsTo(NetsuiteVendorInvoice::class, 'netsuite_vendor_invoice_id');
    }
}