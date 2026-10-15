<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProviderPaymentComplement extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'provider_id', 'uploaded_by', 'uuid', 'issued_at',
        'total', 'currency', 'issuer_rfc', 'receiver_rfc', 'pdf_path', 'xml_path',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $hidden = ['pdf_path', 'xml_path'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** Pagos que ampara este complemento (puede ser más de uno). */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(
            NetsuiteVendorPayment::class,
            'provider_payment_complement_payments',
            'complement_id',
            'netsuite_vendor_payment_id'
        )->withPivot('is_active')->withTimestamps();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}