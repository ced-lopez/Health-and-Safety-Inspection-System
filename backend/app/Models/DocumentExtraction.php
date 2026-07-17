<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id', 'classification', 'extracted_data',
    'business_name', 'owner_name', 'permit_number', 'issuing_authority',
    'date_issued', 'expiration_date', 'certificate_name', 'certificate_number',
    'establishment_name', 'issuing_office', 'issue_date', 'is_expired',
    'missing_requirements', 'confidence_score', 'reviewed_by',
    'reviewed_at', 'ai_processed_at',
])]
class DocumentExtraction extends Model
{
    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'missing_requirements' => 'array',
            'date_issued' => 'date',
            'expiration_date' => 'date',
            'issue_date' => 'date',
            'is_expired' => 'boolean',
            'confidence_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
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
}
