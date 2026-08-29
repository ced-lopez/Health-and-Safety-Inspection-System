<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentExtractionVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attempt' => $this->attempt,
            'ocr_status' => $this->ocr_status,
            'classification' => $this->classification,
            'classification_label' => $this->extracted_data['classification_label'] ?? null,
            'classification_confidence' => $this->classification_confidence !== null
                ? (float) $this->classification_confidence
                : null,
            'confidence_score' => $this->confidence_score !== null ? (float) $this->confidence_score : null,
            'ocr_engine' => $this->ocr_engine,
            'ocr_language' => $this->ocr_language,
            'ocr_passes' => $this->ocr_passes,
            'processing_time_ms' => $this->processing_time_ms,
            'ocr_error' => $this->ocr_error,
            'ocr_text' => $this->ocr_text,
            'processed_at' => ($this->processed_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
