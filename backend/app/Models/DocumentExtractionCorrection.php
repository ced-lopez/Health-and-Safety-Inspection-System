<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtractionCorrection extends Model
{
    protected $table = 'document_extraction_corrections';

    protected $fillable = [
        'document_extraction_id',
        'document_id',
        'field_name',
        'original_value',
        'corrected_value',
        'reason',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'corrected_at' => 'datetime',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class, 'document_extraction_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
