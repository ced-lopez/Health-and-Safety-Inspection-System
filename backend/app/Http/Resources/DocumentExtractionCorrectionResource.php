<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentExtractionCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_name' => $this->field_name,
            'original_value' => $this->original_value,
            'corrected_value' => $this->corrected_value,
            'reason' => $this->reason,
            'corrected_at' => $this->corrected_at?->toIso8601String(),
            'corrected_by' => new UserResource($this->whenLoaded('corrector')),
        ];
    }
}
