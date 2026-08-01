<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ChecklistResource;
use App\Http\Resources\InspectionAssignmentResource;
use App\Http\Resources\InspectionReportResource;
use App\Http\Resources\InspectionResultResource;
use App\Models\Checklist;
use App\Models\Inspection;
use App\Models\InspectionAssignment;
use App\Models\InspectionResult;
use App\Notifications\Concerns\NotifiesRoles;
use App\Notifications\InspectionCompleted;
use App\Notifications\InspectionSubmittedForReview;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionAssignmentController extends BaseApiController
{
    use NotifiesRoles;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = InspectionAssignment::query()
            ->with([
                'inspectionRequest.resident.role',
                'inspectionRequest.inspectionCategory',
                'inspectionRequest.applicationType',
                'inspector.role',
                'assignedBy.role',
                'schedule',
            ]);

        if ($user->role?->slug === 'inspector') {
            $query->where('inspector_id', $user->id);
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $assignments = $query->orderByDesc('assigned_at')->paginate($perPage);

        return $this->success([
            'assignments' => InspectionAssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ], 'Assignments retrieved successfully');
    }

    public function show(InspectionAssignment $inspectionAssignment): JsonResponse
    {
        $inspectionAssignment->load([
            'inspectionRequest.resident.role',
            'inspectionRequest.inspectionCategory',
            'inspectionRequest.applicationType',
            'inspectionRequest.establishment',
            'inspectionRequest.documents.uploader.role',
            'inspector.role',
            'assignedBy.role',
            'schedule',
        ]);

        return $this->success(
            new InspectionAssignmentResource($inspectionAssignment),
            'Assignment retrieved successfully'
        );
    }

    public function start(InspectionAssignment $inspectionAssignment): JsonResponse
    {
        if ($inspectionAssignment->status !== 'assigned' && $inspectionAssignment->status !== 'downloaded') {
            return $this->error('Assignment cannot be started in its current state', 422);
        }

        $inspectionAssignment->update([
            'status' => 'in_progress',
            'server_version' => $inspectionAssignment->server_version + 1,
        ]);

        AuditLogger::log(
            $request->user(),
            'Inspections',
            'Started',
            'Started inspection for request '.($inspectionAssignment->inspectionRequest?->request_number ?? '#'.$inspectionAssignment->inspection_request_id),
            $inspectionAssignment,
            $request,
            event: 'inspection.started',
        );

        return $this->success(
            new InspectionAssignmentResource($inspectionAssignment->load([
                'inspectionRequest.inspectionCategory', 'inspector.role',
            ])),
            'Inspection started successfully'
        );
    }

    public function submit(Request $request, InspectionAssignment $inspectionAssignment): JsonResponse
    {
        if ($inspectionAssignment->status !== 'in_progress') {
            return $this->error('Only in-progress inspections can be submitted', 422);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $inspectionAssignment->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'server_version' => $inspectionAssignment->server_version + 1,
            'notes' => $validated['notes'] ?? $inspectionAssignment->notes,
        ]);

        $inspectionAssignment->inspectionRequest->update([
            'status' => 'inspection_completed',
        ]);

        $inspectionRequest = $inspectionAssignment->inspectionRequest;

        $inspectionRequest->resident?->notify(new InspectionCompleted(
            $inspectionRequest->request_number,
            $inspectionRequest->applicant_name,
            'Completed - submitted for review',
        ));

        $this->notifyRoles(new InspectionSubmittedForReview(
            $inspectionRequest->request_number,
            $inspectionAssignment->inspector?->name ?? 'The inspector',
            $inspectionRequest->business_name ?: $inspectionRequest->applicant_name,
        ), ['administrator', 'barangay_staff']);

        AuditLogger::log(
            $request->user(),
            'Inspections',
            'Completed',
            "Completed and submitted inspection for request {$inspectionRequest->request_number}",
            $inspectionAssignment,
            $request,
            event: 'inspection.completed',
        );

        return $this->success(
            new InspectionAssignmentResource($inspectionAssignment->load([
                'inspectionRequest', 'inspector.role', 'assignedBy.role',
            ])),
            'Inspection submitted successfully'
        );
    }

    public function checklist(Request $request, InspectionAssignment $inspectionAssignment): JsonResponse
    {
        $this->authorizeAssignment($request, $inspectionAssignment);

        $inspection = $this->inspectionFor($inspectionAssignment);

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

    public function saveChecklist(Request $request, InspectionAssignment $inspectionAssignment): JsonResponse
    {
        $this->authorizeAssignment($request, $inspectionAssignment);

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*.checklist_item_id' => ['required', 'exists:checklist_items,id'],
            'results.*.compliance_status' => ['required', 'in:compliant,non_compliant,needs_correction'],
            'results.*.remarks' => ['nullable', 'string'],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*' => ['nullable', 'array'],
            'evidence_files.*.*' => ['file', 'image', 'max:5120'],
        ]);

        $inspection = $this->inspectionFor($inspectionAssignment);

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

            InspectionResult::query()->updateOrCreate(
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
        }

        return $this->success(null, 'Compliance checklist saved successfully');
    }

    public function report(Request $request, InspectionAssignment $inspectionAssignment): JsonResponse
    {
        $this->authorizeAssignment($request, $inspectionAssignment);

        $inspection = $this->inspectionFor($inspectionAssignment)->load([
            'establishment',
            'inspector.role',
            'results.checklistItem.checklist',
            'results.assessor.role',
            'violations',
        ]);

        return $this->success(
            new InspectionReportResource($inspection),
            'Inspection report retrieved successfully'
        );
    }

    public function updateReport(Request $request, InspectionAssignment $inspectionAssignment): JsonResponse
    {
        $this->authorizeAssignment($request, $inspectionAssignment);

        $validated = $request->validate([
            'overall_assessment' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $inspection = $this->inspectionFor($inspectionAssignment);
        $inspection->update([
            'overall_assessment' => $validated['overall_assessment'] ?? null,
            'recommendations' => $validated['recommendations'] ?? null,
        ]);

        if (array_key_exists('notes', $validated)) {
            $inspectionAssignment->update(['notes' => $validated['notes']]);
        }

        $inspection->load([
            'establishment',
            'inspector.role',
            'results.checklistItem.checklist',
            'results.assessor.role',
            'violations',
        ]);

        return $this->success(
            new InspectionReportResource($inspection),
            'Inspection report updated successfully'
        );
    }

    private function authorizeAssignment(Request $request, InspectionAssignment $assignment): void
    {
        $user = $request->user();

        abort_if(
            $user->role?->slug === 'inspector' && $assignment->inspector_id !== $user->id,
            403,
            'You are not assigned to this inspection'
        );
    }

    private function inspectionFor(InspectionAssignment $assignment): Inspection
    {
        $request = $assignment->inspectionRequest;

        $inspection = Inspection::query()
            ->where('inspection_request_id', $assignment->inspection_request_id)
            ->first();

        if (! $inspection && $request?->establishment_id) {
            $inspection = Inspection::query()
                ->where('establishment_id', $request->establishment_id)
                ->where('inspector_id', $assignment->inspector_id)
                ->whereNull('inspection_schedule_id')
                ->latest('id')
                ->first();
        }

        if (! $inspection) {
            $inspection = Inspection::create([
                'inspection_request_id' => $assignment->inspection_request_id,
                'establishment_id' => $request?->establishment_id,
                'inspector_id' => $assignment->inspector_id,
                'inspection_date' => today(),
                'status' => 'ongoing',
                'started_at' => now(),
            ]);
        }

        return $inspection;
    }
}
