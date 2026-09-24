<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Violation\StoreViolationRequest;
use App\Http\Requests\Violation\UpdateViolationRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\ViolationEvidenceResource;
use App\Http\Resources\ViolationResource;
use App\Models\Inspection;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationEvidence;
use App\Notifications\Concerns\NotifiesRoles;
use App\Notifications\ViolationFiled;
use App\Notifications\ViolationNotice;
use App\Services\AuditLogger;
use App\Services\DocumentPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ViolationController extends BaseApiController
{
    use NotifiesRoles;

    public function __construct(private readonly DocumentPdfService $pdfService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Violation::query()
            ->with('establishment')
            ->withCount('evidence');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity') && $request->input('severity') !== 'all') {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->trim()->toString()).'%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$search])
                    ->orWhereHas('establishment', function ($establishmentQuery) use ($search) {
                        $establishmentQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(registration_number) LIKE ?', [$search]);
                    });
            });
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $violations = $query
            ->orderByRaw("CASE severity WHEN 'major' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderByRaw('correction_deadline IS NULL')
            ->orderBy('correction_deadline')
            ->latest()
            ->paginate($perPage);

        return $this->success([
            'violations' => ViolationResource::collection($violations),
            'meta' => [
                'current_page' => $violations->currentPage(),
                'last_page' => $violations->lastPage(),
                'per_page' => $violations->perPage(),
                'total' => $violations->total(),
            ],
        ], 'Violations listed successfully');
    }

    public function options(): JsonResponse
    {
        $inspections = Inspection::query()
            ->with(['establishment', 'results.checklistItem'])
            ->orderByDesc('inspection_date')
            ->limit(100)
            ->get()
            ->map(fn (Inspection $inspection) => [
                'id' => $inspection->id,
                'inspection_date' => $inspection->inspection_date?->toDateString(),
                'status' => $inspection->status,
                'establishment' => [
                    'id' => $inspection->establishment?->id,
                    'name' => $inspection->establishment?->name,
                    'registration_number' => $inspection->establishment?->registration_number,
                ],
                'results' => $inspection->results->map(fn ($result) => [
                    'id' => $result->id,
                    'checklist_item_id' => $result->checklist_item_id,
                    'item_title' => $result->checklistItem?->title,
                    'item_category' => $result->checklistItem?->category,
                    'compliance_status' => $result->compliance_status,
                ])->values(),
            ]);

        $assignees = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', ['administrator', 'barangay_staff', 'inspector']))
            ->with('role')
            ->orderBy('name')
            ->get();

        return $this->success([
            'inspections' => $inspections,
            'assignees' => UserResource::collection($assignees),
        ], 'Violation options retrieved successfully');
    }

    public function store(StoreViolationRequest $request): JsonResponse
    {
        $payload = $this->payloadWithInspection($request->validated());
        $payload['reported_by'] = $request->user()->id;

        if ($payload['status'] === 'resolved') {
            $payload['resolved_at'] = now();
            $payload['resolved_by'] = $request->user()->id;
        }

        $violation = Violation::query()->create($payload);

        $this->notifyViolationNotice($violation, $request);

        AuditLogger::log(
            $request->user(),
            'Violations',
            'Filed',
            "Filed violation: {$violation->title}",
            $violation,
            $request,
            newValues: [
                'severity' => $violation->severity,
                'status' => $violation->status,
            ],
        );

        $this->notifyRoles(new ViolationFiled(
            $violation->title,
            $violation->establishment?->name ?? 'Establishment',
            $violation->severity,
        ), ['administrator', 'barangay_staff']);

        return $this->success(
            new ViolationResource($this->loadViolation($violation)),
            'Violation created successfully',
            201
        );
    }

    public function show(Violation $violation): JsonResponse
    {
        return $this->success(
            new ViolationResource($this->loadViolation($violation)),
            'Violation details retrieved successfully'
        );
    }

    public function pdf(Violation $violation): Response
    {
        $violation = $this->loadViolation($violation);

        return $this->pdfService->violationNoticePdf($violation)
            ->stream('violation-notice-'.$violation->id.'.pdf');
    }

    public function update(UpdateViolationRequest $request, Violation $violation): JsonResponse
    {
        $payload = $this->payloadWithInspection($request->validated());

        if ($payload['status'] === 'resolved' && $violation->status !== 'resolved') {
            $payload['resolved_at'] = now();
            $payload['resolved_by'] = $request->user()->id;
        }

        if ($payload['status'] !== 'resolved') {
            $payload['resolved_at'] = null;
            $payload['resolved_by'] = null;
        }

        $oldStatus = $violation->status;

        $violation->update($payload);

        $this->notifyViolationNotice($violation, $request);

        AuditLogger::log(
            $request->user(),
            'Violations',
            'Updated',
            "Updated violation: {$violation->title}",
            $violation,
            $request,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $violation->status],
        );

        return $this->success(
            new ViolationResource($this->loadViolation($violation)),
            'Violation updated successfully'
        );
    }

    public function destroy(Violation $violation): JsonResponse
    {
        $violation->delete();

        AuditLogger::log(
            request()->user(),
            'Violations',
            'Deleted',
            "Archived violation: {$violation->title} (ID {$violation->id})",
            $violation,
            request(),
        );

        return $this->success(null, 'Violation archived successfully');
    }

    public function storeEvidence(Request $request, Violation $violation): JsonResponse
    {
        $validated = $request->validate([
            'evidence_type' => ['required', 'in:initial,corrective'],
            'description' => ['nullable', 'string'],
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $evidenceIds = [];

        foreach ($request->file('files', []) as $file) {
            $evidence = ViolationEvidence::query()->create([
                'violation_id' => $violation->id,
                'uploaded_by' => $request->user()->id,
                'file_path' => $file->store('violation-evidence', 'public'),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'evidence_type' => $validated['evidence_type'],
                'description' => $validated['description'] ?? null,
            ]);

            $evidenceIds[] = $evidence->id;
        }

        AuditLogger::log(
            $request->user(),
            'Violations',
            'Evidence Uploaded',
            'Uploaded '.count($evidenceIds)." evidence file(s) for violation: {$violation->title}",
            $violation,
            $request,
            newValues: ['evidence_ids' => $evidenceIds],
        );

        return $this->success(
            ViolationEvidenceResource::collection(
                ViolationEvidence::query()
                    ->whereIn('id', $evidenceIds)
                    ->with('uploader.role')
                    ->get()
            ),
            'Violation evidence uploaded successfully',
            201
        );
    }

    private function payloadWithInspection(array $payload): array
    {
        $inspection = Inspection::query()->findOrFail($payload['inspection_id']);
        $payload['establishment_id'] = $inspection->establishment_id;

        return $payload;
    }

    private function notifyViolationNotice(Violation $violation, Request $request): void
    {
        $inspectionRequest = $violation->inspection?->inspectionRequest;

        if (! $inspectionRequest) {
            return;
        }

        if ($inspectionRequest->status === 'inspection_completed') {
            $inspectionRequest->update(['status' => 'violation_notice_issued']);
        }

        $deadline = optional($violation->correction_deadline)->format('M d, Y') ?? '7 days from notice';

        $inspectionRequest->resident?->notify(new ViolationNotice(
            $inspectionRequest->request_number,
            $violation->title,
            $deadline,
            $inspectionRequest->applicant_name,
        ));
    }

    private function loadViolation(Violation $violation): Violation
    {
        return $violation->load([
            'establishment',
            'inspection.establishment',
            'inspection.inspector.role',
            'inspectionResult.checklistItem.checklist',
            'reporter.role',
            'assignee.role',
            'resolver.role',
            'evidence.uploader.role',
        ]);
    }
}
