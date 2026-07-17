<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViolationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_id' => $this->inspection_id,
            'establishment_id' => $this->establishment_id,
            'inspection_result_id' => $this->inspection_result_id,
            'reported_by' => $this->reported_by,
            'assigned_to' => $this->assigned_to,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'correction_deadline' => $this->correction_deadline?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'evidence_count' => $this->evidence_count ?? (
                $this->relationLoaded('evidence') ? $this->evidence->count() : 0
            ),
            'establishment' => new EstablishmentResource($this->whenLoaded('establishment')),
            'inspection' => $this->relationLoaded('inspection') && $this->inspection
                ? [
                    'id' => $this->inspection->id,
                    'inspection_date' => $this->inspection->inspection_date?->toDateString(),
                    'status' => $this->inspection->status,
                    'establishment' => $this->inspection->relationLoaded('establishment')
                        ? new EstablishmentResource($this->inspection->establishment)
                        : null,
                    'inspector' => $this->inspection->relationLoaded('inspector')
                        ? new UserResource($this->inspection->inspector)
                        : null,
                ]
                : null,
            'inspection_result' => new InspectionResultResource($this->whenLoaded('inspectionResult')),
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'resolver' => new UserResource($this->whenLoaded('resolver')),
            'evidence' => ViolationEvidenceResource::collection($this->whenLoaded('evidence')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
