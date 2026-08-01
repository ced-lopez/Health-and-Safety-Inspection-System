<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ChecklistResource;
use App\Http\Resources\InspectionResultResource;
use App\Models\Checklist;
use App\Models\InspectionResult;
use App\Models\InspectionSchedule;
use App\Services\AuditLogger;
use App\Services\InspectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceChecklistController extends BaseApiController
{
    public function show(InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $inspection = InspectionSyncService::sync($inspectionSchedule);

        $checklists = Checklist::query()
            ->where('is_active', true)
            ->with('items')
            ->orderBy('category')
            ->get();

        $results = InspectionResult::query()
            ->where('inspection_id', $inspection->id)
            ->with(['checklistItem', 'assessor.role'])
            ->get();

        return $this->success([
            'inspection' => [
                'id' => $inspection->id,
                'status' => $inspection->status,
                'inspection_date' => $inspection->inspection_date?->toDateString(),
            ],
            'checklists' => ChecklistResource::collection($checklists),
            'results' => InspectionResultResource::collection($results),
        ], 'Compliance checklist retrieved successfully');
    }

    public function store(Request $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*.checklist_item_id' => ['required', 'exists:checklist_items,id'],
            'results.*.compliance_status' => ['required', 'in:compliant,non_compliant,needs_correction'],
            'results.*.remarks' => ['nullable', 'string'],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*' => ['nullable', 'array'],
            'evidence_files.*.*' => ['file', 'image', 'max:5120'],
        ]);

        $inspection = InspectionSyncService::sync($inspectionSchedule);
        $savedIds = [];

        foreach ($validated['results'] as $result) {
            $itemId = (int) $result['checklist_item_id'];
            $evidencePaths = [];

            foreach ($request->file("evidence_files.$itemId", []) as $file) {
                $evidencePaths[] = $file->store('inspection-evidence', 'public');
            }

            $existing = InspectionResult::query()
                ->where('inspection_id', $inspection->id)
                ->where('checklist_item_id', $itemId)
                ->first();

            $mergedEvidence = array_values(array_filter(array_merge(
                $existing?->evidence_paths ?? [],
                $evidencePaths
            )));

            $saved = InspectionResult::query()->updateOrCreate(
                [
                    'inspection_id' => $inspection->id,
                    'checklist_item_id' => $itemId,
                ],
                [
                    'compliance_status' => $result['compliance_status'],
                    'remarks' => $result['remarks'] ?? null,
                    'evidence_paths' => $mergedEvidence,
                    'assessed_by' => $request->user()->id,
                ]
            );

            $savedIds[] = $saved->id;
        }

        AuditLogger::log(
            $request->user(),
            'Inspections',
            'Checklist Saved',
            'Saved compliance checklist for inspection #'.$inspection->id.' ('.count($savedIds).' item(s))',
            $inspection,
            $request,
        );

        $results = InspectionResult::query()
            ->whereIn('id', $savedIds)
            ->with(['checklistItem', 'assessor.role'])
            ->get();

        return $this->success(
            InspectionResultResource::collection($results),
            'Compliance checklist saved successfully'
        );
    }
}
