<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'documentable_type', 'documentable_id', 'uploaded_by',
    'document_type', 'file_path', 'file_name', 'original_name',
    'mime_type', 'file_size', 'status',
])]
class Document extends Model
{
    use SoftDeletes;

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function extraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class);
    }
}
