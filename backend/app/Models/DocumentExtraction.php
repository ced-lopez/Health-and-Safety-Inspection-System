<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentExtraction extends Model
{
    public const OCR_STATUS_PENDING = 'pending';

    public const OCR_STATUS_PROCESSING = 'processing';

    public const OCR_STATUS_COMPLETED = 'completed';

    public const OCR_STATUS_NEEDS_REVIEW = 'needs_review';

    public const OCR_STATUS_FAILED = 'failed';

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_REJECTED = 'rejected';

    protected $fillable = [
        'document_id',
        'ocr_status',
        'verification_status',
        'ocr_engine',
        'ocr_language',
        'ocr_raw_text',
        'ocr_passes',
        'ocr_versions',
        'classification',
        'classification_confidence',
        'extracted_data',
        'ocr_text',
        'processing_time_ms',
        'ocr_error',
        'business_name',
        'owner_name',
        'permit_number',
        'issuing_authority',
        'date_issued',
        'expiration_date',
        'certificate_name',
        'certificate_number',
        'establishment_name',
        'issuing_office',
        'issue_date',
        'is_expired',
        'missing_requirements',
        'low_confidence_fields',
        'confidence_score',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'requested_reupload_at',
        'last_edited_by',
        'last_edited_at',
        'ai_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'missing_requirements' => 'array',
            'low_confidence_fields' => 'array',
            'ocr_versions' => 'array',
            'date_issued' => 'date',
            'expiration_date' => 'date',
            'issue_date' => 'date',
            'is_expired' => 'boolean',
            'classification_confidence' => 'float',
            'confidence_score' => 'float',
            'processing_time_ms' => 'integer',
            'ocr_passes' => 'integer',
            'reviewed_at' => 'datetime',
            'requested_reupload_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'ai_processed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(DocumentExtractionCorrection::class, 'document_extraction_id')
            ->latest('corrected_at');
    }
}
