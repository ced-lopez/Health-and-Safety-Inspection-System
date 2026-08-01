<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentExtractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classification' => $this->classification,
            'extracted_data' => $this->extracted_data,
            'business_name' => $this->business_name,
            'owner_name' => $this->owner_name,
            'permit_number' => $this->permit_number,
            'issuing_authority' => $this->issuing_authority,
            'issuing_office' => $this->issuing_office,
            'date_issued' => $this->date_issued?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'confidence_score' => $this->confidence_score,
            'is_expired' => $this->is_expired,
            'missing_requirements' => $this->missing_requirements,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'ai_processed_at' => $this->ai_processed_at?->toIso8601String(),
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
        ];
    }
}
