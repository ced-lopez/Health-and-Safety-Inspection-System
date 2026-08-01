<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'downloaded_at' => $this->downloaded_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'server_version' => $this->server_version,
            'notes' => $this->notes,
            'scheduled_at' => $this->whenLoaded('schedule', fn () => $this->schedule?->scheduled_at?->toIso8601String()),
            'schedule_id' => $this->whenLoaded('schedule', fn () => $this->schedule?->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'inspection_request' => new InspectionRequestResource($this->whenLoaded('inspectionRequest')),
            'inspector' => new UserResource($this->whenLoaded('inspector')),
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
        ];
    }
}
