<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'establishment_id' => $this->establishment_id,
            'inspector_id' => $this->inspector_id,
            'scheduled_by' => $this->scheduled_by,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'scheduled_time' => $this->scheduled_time,
            'status' => $this->status,
            'notes' => $this->notes,
            'establishment' => new EstablishmentResource($this->whenLoaded('establishment')),
            'inspector' => new UserResource($this->whenLoaded('inspector')),
            'scheduler' => new UserResource($this->whenLoaded('scheduler')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
