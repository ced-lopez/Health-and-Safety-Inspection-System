<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_schedule_id' => $this->inspection_schedule_id,
            'inspection_request_id' => $this->inspection_request_id,
            'establishment_id' => $this->establishment_id,
            'inspector_id' => $this->inspector_id,
            'inspection_date' => $this->inspection_date?->toDateString(),
            'status' => $this->status,
            'overall_assessment' => $this->overall_assessment,
            'recommendations' => $this->recommendations,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'establishment' => $this->relationLoaded('establishment') && $this->establishment
                ? new EstablishmentResource($this->establishment)
                : null,
            'inspector' => $this->relationLoaded('inspector') && $this->inspector
                ? new UserResource($this->inspector)
                : null,
            'violations_count' => $this->relationLoaded('violations')
                ? $this->violations->count()
                : $this->violations_count ?? 0,
        ];
    }
}