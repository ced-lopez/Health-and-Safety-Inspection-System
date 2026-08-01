<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $results = $this->results->map(function ($result) {
            $item = $result->checklistItem;

            return [
                'id' => $result->id,
                'checklist_item_id' => $result->checklist_item_id,
                'checklist_name' => $item?->checklist?->name,
                'checklist_category' => $item?->checklist?->category,
                'item_category' => $item?->category,
                'item_title' => $item?->title,
                'compliance_status' => $result->compliance_status,
                'remarks' => $result->remarks,
                'evidence_paths' => $result->evidence_paths ?? [],
                'assessor' => $result->relationLoaded('assessor')
                    ? new UserResource($result->assessor)
                    : null,
            ];
        });

        $violations = $this->violations->map(fn ($violation) => [
            'id' => $violation->id,
            'title' => $violation->title,
            'description' => $violation->description,
            'severity' => $violation->severity,
            'status' => $violation->status,
            'correction_deadline' => $violation->correction_deadline?->toDateString(),
        ]);

        return [
            'id' => $this->id,
            'inspection_schedule_id' => $this->inspection_schedule_id,
            'inspection_date' => $this->inspection_date?->toDateString(),
            'status' => $this->status,
            'overall_assessment' => $this->overall_assessment,
            'recommendations' => $this->recommendations,
            'establishment' => $this->whenLoaded('establishment', fn () => $this->establishment ? new EstablishmentResource($this->establishment) : null),
            'inspector' => new UserResource($this->whenLoaded('inspector')),
            'schedule' => new InspectionScheduleResource($this->whenLoaded('schedule')),
            'summary' => [
                'total_items' => $results->count(),
                'compliant' => $results->where('compliance_status', 'compliant')->count(),
                'non_compliant' => $results->where('compliance_status', 'non_compliant')->count(),
                'needs_correction' => $results->where('compliance_status', 'needs_correction')->count(),
                'violations' => $violations->count(),
            ],
            'results' => $results->values(),
            'violations' => $violations->values(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
