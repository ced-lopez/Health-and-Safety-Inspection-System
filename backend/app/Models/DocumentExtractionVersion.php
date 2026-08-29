<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtractionVersion extends Model
{
    protected $table = 'document_extraction_versions';

    protected $fillable = [
        'document_id',
        'attempt',
        'ocr_status',
        'classification',
        'classification_confidence',
        'extracted_data',
        'ocr_text',
        'ocr_raw_text',
        'processing_time_ms',
        'ocr_engine',
        'ocr_language',
        'ocr_passes',
        'confidence_score',
        'ocr_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'classification_confidence' => 'float',
            'confidence_score' => 'float',
            'processing_time_ms' => 'integer',
            'ocr_passes' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
