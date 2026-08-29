<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentExtractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $extractedData = $this->extracted_data ?? [];

        return [
            'id' => $this->id,
            'classification' => $this->classification,
            'classification_label' => $extractedData['classification_label'] ?? null,
            'classification_confidence' => $this->classification_confidence !== null
                ? (float) $this->classification_confidence
                : null,
            'fields' => $extractedData['fields'] ?? [],
            'extracted_data' => $extractedData,
            'business_name' => $this->business_name,
            'owner_name' => $this->owner_name,
            'permit_number' => $this->permit_number,
            'issuing_authority' => $this->issuing_authority,
            'issuing_office' => $this->issuing_office,
            'date_issued' => $this->date_issued?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'confidence_score' => $this->confidence_score,
            'ocr_status' => $this->ocr_status,
            'verification_status' => $this->verification_status ?? 'pending',
            'reject_reason' => $this->reject_reason,
            'requested_reupload_at' => $this->requested_reupload_at?->toIso8601String(),
            'ocr_engine' => $this->ocr_engine,
            'ocr_language' => $this->ocr_language,
            'ocr_passes' => $this->ocr_passes,
            'ocr_versions' => $this->ocr_versions,
            'ocr_text' => $this->ocr_text ?? ($extractedData['ocr_text'] ?? null),
            'ocr_raw_text' => $this->ocr_raw_text ?? ($extractedData['ocr_text'] ?? null),
            'processing_time_ms' => $this->processing_time_ms,
            'ocr_error' => $this->ocr_error,
            'low_confidence_fields' => $this->low_confidence_fields ?? ($extractedData['low_confidence_fields'] ?? []),
            'region_results' => $extractedData['region_results'] ?? null,
            'id_ambiguity' => $extractedData['id_ambiguity'] ?? null,
            'validation' => $extractedData['validation'] ?? ['is_valid' => true, 'warnings' => []],
            'is_expired' => $this->is_expired,
            'missing_requirements' => $this->missing_requirements,
            'verified' => $this->verification_status === 'verified',
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'ai_processed_at' => $this->ai_processed_at?->toIso8601String(),
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
        ];
    }
}
