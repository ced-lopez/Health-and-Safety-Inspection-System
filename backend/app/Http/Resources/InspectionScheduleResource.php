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
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'schedule_type' => $this->schedule_type,
            'inspection_request_id' => $this->inspection_request_id,
            'inspection_assignment_id' => $this->inspection_assignment_id,
            'server_version' => $this->server_version,
            'status' => $this->status,
            'notes' => $this->notes,
            'establishment' => $this->whenLoaded('establishment', fn () => $this->establishment ? new EstablishmentResource($this->establishment) : null),
            'inspector' => new UserResource($this->whenLoaded('inspector')),
            'scheduler' => new UserResource($this->whenLoaded('scheduler')),
            'request' => $this->whenLoaded('request', fn () => $this->request ? new InspectionRequestResource($this->request) : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
