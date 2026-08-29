<?php

namespace App\Http\Resources;

use App\Models\InspectionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcrResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $extraction = $this->whenLoaded('extraction');
        $context = $this->whenLoaded('documentable');

        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'original_name' => $this->original_name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,

            'applicant' => $this->contextApplicant($context),
            'business_name' => $context instanceof InspectionRequest ? $context->business_name : null,
            'request_number' => $context instanceof InspectionRequest ? $context->request_number : null,

            'classification' => $extraction?->classification,
            'classification_label' => $extraction?->extracted_data['classification_label'] ?? null,
            'ocr_status' => $extraction?->ocr_status,
            'verification_status' => $extraction?->verification_status,
            'confidence_score' => $extraction?->confidence_score !== null ? (float) $extraction->confidence_score : null,
            'processed_at' => ($extraction?->ai_processed_at ?? $extraction?->updated_at)?->toIso8601String(),

            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'extraction' => $extraction ? new DocumentExtractionResource($extraction) : null,
            'corrections' => DocumentExtractionCorrectionResource::collection($this->whenLoaded('corrections')),
            'versions' => DocumentExtractionVersionResource::collection($this->whenLoaded('ocrVersions')),
        ];
    }

    private function contextApplicant(mixed $context): ?string
    {
        if (! $context instanceof InspectionRequest) {
            return null;
        }

        if ($context->applicant_name !== null) {
            return $context->applicant_name;
        }

        return $context->resident?->name;
    }
}
