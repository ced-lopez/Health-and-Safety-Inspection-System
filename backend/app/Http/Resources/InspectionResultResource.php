<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_id' => $this->inspection_id,
            'checklist_item_id' => $this->checklist_item_id,
            'compliance_status' => $this->compliance_status,
            'remarks' => $this->remarks,
            'evidence_paths' => $this->evidence_paths ?? [],
            'assessed_by' => $this->assessed_by,
            'checklist_item' => new ChecklistItemResource($this->whenLoaded('checklistItem')),
            'assessor' => new UserResource($this->whenLoaded('assessor')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
