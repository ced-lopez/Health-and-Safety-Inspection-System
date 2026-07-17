<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'violation_id', 'uploaded_by', 'file_path', 'file_name',
    'mime_type', 'evidence_type', 'description',
])]
class ViolationEvidence extends Model
{
    protected $table = 'violation_evidence';

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
