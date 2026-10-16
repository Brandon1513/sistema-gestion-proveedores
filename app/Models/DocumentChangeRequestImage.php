<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChangeRequestImage extends Model
{
    protected $table = 'document_change_request_images';

    protected $fillable = [
        'change_request_id',
        'file_path',
        'original_filename',
        'mime_type',
        'size_kb',
    ];

    protected $hidden = ['file_path'];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentChangeRequest::class, 'change_request_id');
    }
}