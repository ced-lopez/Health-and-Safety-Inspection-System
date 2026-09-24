<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\InspectionRequest\ReviewInspectionRequest;
use App\Http\Requests\InspectionRequest\StoreInspectionRequest;
use App\Http\Resources\ApplicationTypeResource;
use App\Http\Resources\InspectionCategoryResource;
use App\Http\Resources\InspectionRequestResource;
use App\Models\ApplicationType;
use App\Models\DocumentRequirementRule;
use App\Models\Establishment;
use App\Models\InspectionAssignment;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\Concerns\NotifiesRoles;
use App\Notifications\InspectorAssigned;
use App\Notifications\MissingRequirements;
use App\Notifications\NewApplicationSubmitted;
use App\Notifications\NewInspectionAssignment;
use App\Notifications\PreferredScheduleSubmitted;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InspectionRequestController extends BaseApiController
{
    use NotifiesRoles;

    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = InspectionRequest::query()
            ->with(['resident.role', 'inspectionCategory', 'applicationType', 'establishment', 'reviewedBy'])
            ->withCount('documents')
            ->withExists([
                'payments as application_fee_paid' => fn ($q) => $q->where('type', PaymentService::TYPE_APPLICATION)->where('status', 'paid'),
                'payments as clearance_fee_paid' => fn ($q) => $q->where('type', PaymentService::TYPE_CLEARANCE)->where('status', 'paid'),
            ]);

        if ($user->role?->slug === 'resident') {
            $query->where('resident_id', $user->id);
        }

        if ($request->filled('status') && $request->input('status') === 'active') {
            $query->whereIn('status', ['submitted', 'under_review', 'requirements_incomplete']);
        } elseif ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('inspection_category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.strtolower(trim((string) $request->input('search'))).'%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(request_number) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(business_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(applicant_name) LIKE ?', [$search])
                    ->orWhereHas('resident', function ($r) use ($search) {
                        $r->whereRaw('LOWER(name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                    });
            });
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $requests = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'inspection_requests' => InspectionRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 'Inspection requests retrieved successfully');
    }

    public function options(): JsonResponse
    {
        $categories = InspectionCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $applicationTypes = ApplicationType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success([
            'categories' => InspectionCategoryResource::collection($categories),
            'application_types' => ApplicationTypeResource::collection($applicationTypes),
        ], 'Inspection request options retrieved successfully');
    }

    public function documentRequirements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inspection_category_id' => ['required', 'exists:inspection_categories,id'],
            'application_type_id' => ['required', 'exists:application_types,id'],
            'sub_path' => ['nullable', 'string', 'in:household,commercial_kennel,backyard_micro_scale'],
        ]);

        $rules = $this->requirementRules(
            $validated['inspection_category_id'],
            $validated['application_type_id'],
            $validated['sub_path'] ?? null
        );

        $items = $rules->map(fn (DocumentRequirementRule $rule) => [
            'document_type' => $rule->document_type,
            'document_name' => $rule->document_name,
            'is_required' => (bool) $rule->is_required,
            'requires_expiration_check' => (bool) $rule->requires_expiration_check,
            'sub_path' => $rule->sub_path,
            'notes' => $rule->notes,
        ])->values();

        return $this->success([
            'requirements' => $items,
            'summary' => [
                'required_count' => $items->where('is_required', true)->count(),
            ],
        ], 'Document requirements retrieved successfully');
    }

    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        if (! empty($validated['establishment_id'])) {
            $establishment = Establishment::query()->find($validated['establishment_id']);

            if ($establishment && $establishment->resident_id !== $user->id && $establishment->ownership_status !== 'unclaimed') {
                return $this->error(
                    'This establishment belongs to another resident.',
                    422,
                    ['establishment_id' => ['You can only link an unclaimed establishment or one that you own.']]
                );
            }
        }

        $category = InspectionCategory::query()->find($validated['inspection_category_id']);

        $blocked = $this->blockPiggeryPoultry($validated, $category, $user, $request);

        if ($blocked) {
            return $blocked;
        }

        if ($category?->slug === 'animal_raising_dogs' && ! in_array($validated['sub_path'] ?? null, ['household', 'commercial_kennel'], true)) {
            return $this->error(
                'Please declare whether this is household pet dog keeping or a commercial kennel/breeding operation.',
                422,
                ['sub_path' => ['Please declare whether this is household pet dog keeping or a commercial kennel/breeding operation.']]
            );
        }

        $requestNumber = 'BRGY-REQ-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));

        // Auto-link establishment: if resident didn't link one, create/find one from request data so establishments table is populated.
        $establishmentId = $validated['establishment_id'] ?? null;
        if (empty($establishmentId)) {
            $establishmentId = $this->findOrCreateEstablishmentForRequest($validated, $category, $user);
        }

        $inspectionRequest = InspectionRequest::query()->create([
            'request_number' => $requestNumber,
            'resident_id' => $user->id,
            'inspection_category_id' => $validated['inspection_category_id'],
            'application_type_id' => $validated['application_type_id'],
            'sub_path' => $validated['sub_path'] ?? null,
            'declared_animal_count' => $validated['declared_animal_count'] ?? null,
            'establishment_id' => $establishmentId,
            'applicant_name' => $validated['applicant_name'],
            'applicant_age' => $validated['applicant_age'] ?? null,
            'applicant_address' => $validated['applicant_address'],
            'contact_number' => $validated['contact_number'],
            'email' => $validated['email'],
            'business_name' => $validated['business_name'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->payments->ensurePending($inspectionRequest, PaymentService::TYPE_APPLICATION);

        $inspectionRequest->load([
            'resident.role', 'inspectionCategory', 'applicationType', 'establishment', 'payments.confirmedBy.role',
        ]);

        $categoryName = $inspectionRequest->inspectionCategory?->name ?? 'Inspection';
        $businessName = $inspectionRequest->business_name ?: $inspectionRequest->applicant_name;

        $user->notify(new ApplicationSubmitted(
            $inspectionRequest->request_number,
            $categoryName,
            $inspectionRequest->applicant_name,
        ));

        $this->notifyRoles(new NewApplicationSubmitted(
            $inspectionRequest->request_number,
            $categoryName,
            $inspectionRequest->applicant_name,
            $businessName,
        ), ['administrator', 'barangay_staff']);

        AuditLogger::log(
            $user,
            'Inspection Requests',
            'Submitted',
            "Submitted inspection request {$inspectionRequest->request_number}",
            $inspectionRequest,
            $request,
            event: 'inspection_request.submitted',
        );

        return $this->success(
            new InspectionRequestResource($inspectionRequest),
            'Inspection request submitted successfully',
            201
        );
    }

    public function show(InspectionRequest $inspectionRequest): JsonResponse
    {
        $inspectionRequest->load([
            'resident.role',
            'inspectionCategory',
            'applicationType',
            'establishment',
            'reviewedBy.role',
            'documents.uploader.role',
            'documents.extraction.reviewer.role',
            'inspectionAssignment.inspector.role',
            'inspectionAssignment.assignedBy.role',
            'schedules',
            'payments.confirmedBy.role',
        ]);

        return $this->success(
            new InspectionRequestResource($inspectionRequest),
            'Inspection request retrieved successfully'
        );
    }

    public function setPreferredSchedule(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $user = $request->user();
        $isStaff = in_array($user->role?->slug, ['administrator', 'barangay_staff'], true);

        if (! $isStaff && $inspectionRequest->resident_id !== $user->id) {
            return $this->error('You can only propose a schedule for your own applications', 403);
        }

        $validated = $request->validate([
            'preferred_schedule_at' => ['required', 'date', 'after:now'],
        ]);

        if (in_array($inspectionRequest->status, ['inspection_completed', 'violation_notice_issued', 'follow_up_requested', 'clearance_approved', 'rejected', 'cancelled'], true)) {
            return $this->error('You can no longer propose a schedule for this application.', 422);
        }

        if ($inspectionRequest->schedules()->whereNotNull('scheduled_at')->exists()) {
            return $this->error('This application already has a confirmed inspection schedule.', 422);
        }

        $inspectionRequest->update([
            'preferred_schedule_at' => Carbon::parse($validated['preferred_schedule_at']),
        ]);

        AuditLogger::log(
            $user,
            'Inspection Requests',
            'Schedule Proposed',
            "Preferred schedule proposed for request {$inspectionRequest->request_number}",
            $inspectionRequest,
            $request,
            newValues: [
                'preferred_schedule_at' => $inspectionRequest->preferred_schedule_at->toIso8601String(),
            ],
            event: 'inspection_request.preferred_schedule',
        );

        $inspectionRequest->load([
            'resident.role', 'inspectionCategory', 'applicationType', 'establishment', 'reviewedBy.role',
            'schedules',
        ]);

        if (! $isStaff) {
            $this->notifyRoles(new PreferredScheduleSubmitted(
                $inspectionRequest->request_number,
                $inspectionRequest->business_name ?: $inspectionRequest->applicant_name,
                $inspectionRequest->applicant_name,
                $inspectionRequest->preferred_schedule_at->format('M d, Y \a\t h:i A'),
            ), ['administrator', 'barangay_staff']);
        }

        return $this->success(
            new InspectionRequestResource($inspectionRequest),
            'Preferred schedule submitted. A barangay staff will confirm your inspection schedule.'
        );
    }

    public function clearPreferredSchedule(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $user = $request->user();
        $isStaff = in_array($user->role?->slug, ['administrator', 'barangay_staff'], true);

        if (! $isStaff && $inspectionRequest->resident_id !== $user->id) {
            return $this->error('You can only clear the schedule proposal for your own applications', 403);
        }

        if ($inspectionRequest->schedules()->whereNotNull('scheduled_at')->exists()) {
            return $this->error('This application already has a confirmed inspection schedule.', 422);
        }

        $inspectionRequest->update(['preferred_schedule_at' => null]);

        AuditLogger::log(
            $user,
            'Inspection Requests',
            'Schedule Proposal Removed',
            "Preferred schedule proposal removed for request {$inspectionRequest->request_number}",
            $inspectionRequest,
            $request,
            event: 'inspection_request.preferred_schedule_removed',
        );

        return $this->success(
            new InspectionRequestResource($inspectionRequest->load([
                'resident.role', 'inspectionCategory', 'applicationType', 'establishment', 'reviewedBy.role',
                'schedules',
            ])),
            'Preferred schedule removed'
        );
    }

    public function review(ReviewInspectionRequest $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $validated = $request->validated();

        if (! in_array($validated['status'], ['rejected', 'cancelled'], true)
            && ! $this->payments->isPaid($inspectionRequest, PaymentService::TYPE_APPLICATION)) {
            return $this->error(
                'This request cannot progress past Submitted until the application fee is confirmed paid.',
                422
            );
        }

        $inspectionRequest->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $validated['remarks'] ?? $inspectionRequest->remarks,
        ]);

        AuditLogger::log(
            $request->user(),
            'Inspection Requests',
            $this->reviewAction($validated['status']),
            "Reviewed inspection request {$inspectionRequest->request_number}",
            $inspectionRequest,
            $request,
            newValues: [
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? null,
            ],
            event: 'inspection_request.reviewed',
        );

        $inspectionRequest->load([
            'resident.role', 'inspectionCategory', 'applicationType', 'establishment', 'reviewedBy.role',
        ]);

        if ($validated['status'] === 'requirements_incomplete') {
            $inspectionRequest->resident?->notify(new MissingRequirements(
                $inspectionRequest->request_number,
                $this->missingRequirements($inspectionRequest),
                $inspectionRequest->applicant_name,
            ));
        }

        return $this->success(
            new InspectionRequestResource($inspectionRequest),
            'Inspection request updated successfully'
        );
    }

    public function assign(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $validated = $request->validate([
            'inspector_id' => ['required', 'exists:users,id'],
        ]);

        if ($inspectionRequest->status !== 'approved_for_inspection') {
            return $this->error('Only approved inspection requests can be assigned to an inspector.', 422);
        }

        $alreadyAssigned = $inspectionRequest->inspectionAssignment()
            ->whereIn('status', ['assigned', 'in_progress'])
            ->exists();

        if ($alreadyAssigned) {
            return $this->error('This request already has an active inspector assignment.', 422);
        }

        $inspector = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', 'inspector'))
            ->findOrFail($validated['inspector_id']);

        DB::transaction(function () use ($inspectionRequest, $inspector, $request) {
            $inspectionRequest->update([
                'status' => 'assigned',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            InspectionAssignment::query()->create([
                'inspection_request_id' => $inspectionRequest->id,
                'inspector_id' => $inspector->id,
                'assigned_by' => $request->user()->id,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);
        });

        AuditLogger::log(
            $request->user(),
            'Inspection Requests',
            'Assigned',
            "Assigned inspector {$inspector->name} to request {$inspectionRequest->request_number}",
            $inspectionRequest,
            $request,
            newValues: [
                'inspector_id' => $inspector->id,
            ],
            event: 'inspection_request.assigned',
        );

        $inspectionRequest->load([
            'resident.role', 'inspectionCategory', 'applicationType', 'establishment',
            'reviewedBy.role', 'inspectionAssignment.inspector.role',
        ]);

        $inspectionRequest->resident?->notify(new InspectorAssigned(
            $inspectionRequest->request_number,
            $inspector->name,
            $inspectionRequest->applicant_name,
        ));

        $inspector->notify(new NewInspectionAssignment(
            $inspectionRequest->request_number,
            $inspectionRequest->business_name ?: $inspectionRequest->applicant_name,
            $inspectionRequest->inspectionCategory?->name ?? 'Inspection',
            $inspectionRequest->applicant_name,
        ));

        return $this->success(
            new InspectionRequestResource($inspectionRequest),
            'Inspector assigned successfully'
        );
    }

    public function queue(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), 50);

        $queue = InspectionRequest::query()
            ->whereIn('status', ['approved_for_inspection', 'assigned', 'follow_up_requested'])
            ->with([
                'resident.role',
                'inspectionCategory',
                'applicationType',
                'establishment',
                'inspectionAssignment.inspector.role',
                'inspectionAssignment.assignedBy.role',
            ])
            ->withCount('documents');

        if ($request->boolean('pending_schedule')) {
            $queue->whereDoesntHave('schedules', fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled']));
        }

        $queue = $queue
            ->orderByRaw("CASE WHEN status = 'approved_for_inspection' THEN 0 ELSE 1 END")
            ->orderBy('reviewed_at')
            ->paginate($perPage);

        return $this->success([
            'queue' => InspectionRequestResource::collection($queue),
            'meta' => [
                'current_page' => $queue->currentPage(),
                'last_page' => $queue->lastPage(),
                'per_page' => $queue->perPage(),
                'total' => $queue->total(),
            ],
        ], 'Inspection queue retrieved successfully');
    }

    public function requirements(InspectionRequest $inspectionRequest): JsonResponse
    {
        $rules = $this->requirementRules(
            $inspectionRequest->inspection_category_id,
            $inspectionRequest->application_type_id,
            $inspectionRequest->sub_path
        );

        $documents = $inspectionRequest->documents()
            ->with('extraction')
            ->get();

        $items = $rules->map(function (DocumentRequirementRule $rule) use ($documents) {
            $match = $documents->first(fn ($doc) => $doc->document_type === $rule->document_type);

            return [
                'document_type' => $rule->document_type,
                'document_name' => $rule->document_name,
                'is_required' => (bool) $rule->is_required,
                'requires_expiration_check' => (bool) $rule->requires_expiration_check,
                'uploaded' => $match !== null,
                'verified' => $match?->status === 'verified',
                'expired' => (bool) ($match?->extraction?->is_expired ?? false),
                'document' => $match ? [
                    'id' => $match->id,
                    'file_name' => $match->file_name,
                    'status' => $match->status,
                    'is_expired' => (bool) ($match->extraction?->is_expired ?? false),
                ] : null,
            ];
        })->values();

        $required = $items->filter(fn ($item) => $item['is_required'])->values();
        $requiredCount = $required->count();
        $uploadedCount = $required->filter(fn ($item) => $item['uploaded'])->count();
        $verifiedCount = $required->filter(fn ($item) => $item['verified'])->count();

        return $this->success([
            'requirements' => $items,
            'summary' => [
                'required_count' => $requiredCount,
                'uploaded_count' => $uploadedCount,
                'verified_count' => $verifiedCount,
                'complete' => $requiredCount > 0 && $uploadedCount === $requiredCount,
                'all_verified' => $requiredCount > 0 && $verifiedCount === $requiredCount,
            ],
        ], 'Requirements verification status retrieved successfully');
    }

    private function reviewAction(string $status): string
    {
        return match ($status) {
            'approved_for_inspection' => 'Approved',
            'requirements_incomplete' => 'Requirements Incomplete',
            default => 'Rejected',
        };
    }

    private function missingRequirements(InspectionRequest $inspectionRequest): array
    {
        $rules = $this->requirementRules(
            $inspectionRequest->inspection_category_id,
            $inspectionRequest->application_type_id,
            $inspectionRequest->sub_path
        )->where('is_required', true);

        $uploadedTypes = $inspectionRequest->documents()->pluck('document_type')->all();

        return $rules
            ->reject(fn ($rule) => in_array($rule->document_type, $uploadedTypes, true))
            ->pluck('document_name')
            ->values()
            ->all();
    }

    private function findOrCreateEstablishmentForRequest(array $validated, ?InspectionCategory $category, User $user): ?int
    {
        $name = trim((string) ($validated['business_name'] ?? ''));
        if ($name === '') {
            $name = trim($validated['applicant_name']).' - '.($category?->name ?? 'Establishment');
        }

        // Reuse existing establishment for same resident + same name/address to avoid duplicates on repeated applications
        $existing = Establishment::query()
            ->where('resident_id', $user->id)
            ->where('name', $name)
            ->where('address', $validated['applicant_address'])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $categoryMap = [
            'business_establishments' => 'food_establishment',
            'piggery' => 'piggery',
            'poultry' => 'poultry',
            'animal_raising_dogs' => 'dog_raising_kennel',
        ];

        $estCategory = $categoryMap[$category?->slug ?? ''] ?? 'food_establishment';

        $registrationNumber = 'B178-EST-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));
        // ensure uniqueness in race condition
        while (Establishment::where('registration_number', $registrationNumber)->exists()) {
            $registrationNumber = 'B178-EST-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));
        }

        $establishment = Establishment::query()->create([
            'name' => $name,
            'owner_name' => $validated['applicant_name'],
            'address' => $validated['applicant_address'],
            'business_type' => $category?->name ?? $validated['business_name'] ?? 'General',
            'category' => $estCategory,
            'contact_number' => $validated['contact_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'registration_number' => $registrationNumber,
            'status' => 'active',
            'resident_id' => $user->id,
            'ownership_status' => 'linked',
        ]);

        return $establishment->id;
    }

    private function requirementRules(int $categoryId, int $applicationTypeId, ?string $subPath)
    {
        // Archived: only whitelist remains active in resident upload (barangay_id removed, proof_of_location renamed).
        $whitelist = ['government_id', 'proof_of_location'];

        return DocumentRequirementRule::query()
            ->where('inspection_category_id', $categoryId)
            ->where('application_type_id', $applicationTypeId)
            ->whereIn('document_type', $whitelist)
            ->where(function ($query) use ($subPath) {
                $query->whereNull('sub_path')
                    ->orWhere('sub_path', $subPath);
            })
            ->orderByDesc('is_required')
            ->orderBy('id')
            ->get();
    }

    private function blockPiggeryPoultry(array $validated, ?InspectionCategory $category, User $user, Request $request): ?JsonResponse
    {
        if (! in_array($category?->slug, ['piggery', 'poultry'], true)) {
            return null;
        }

        $subPath = $validated['sub_path'] ?? null;
        $animalCount = $validated['declared_animal_count'] ?? null;

        if ($subPath !== 'backyard_micro_scale') {
            AuditLogger::log(
                $user,
                'Inspection Requests',
                'Blocked',
                'Blocked commercial-scale '.$category->name.' application submission',
                null,
                $request,
                newValues: [
                    'category' => $category->slug,
                    'sub_path' => $subPath,
                    'reason' => 'commercial_scale_blocked',
                ],
                event: 'inspection_request.blocked',
            );

            return $this->error(
                'Zoning clearances cannot be issued for commercial-scale '.$category->name.' in this barangay. Only backyard micro-scale (2–5 animals, personal use) may be applied for.',
                422,
                ['sub_path' => ['Commercial-scale '.$category->name.' is not allowed in this barangay.']]
            );
        }

        if ($animalCount === null || (int) $animalCount < 2 || (int) $animalCount > 5) {
            AuditLogger::log(
                $user,
                'Inspection Requests',
                'Blocked',
                'Blocked '.$category->name.' application with invalid animal count',
                null,
                $request,
                newValues: [
                    'category' => $category->slug,
                    'declared_animal_count' => $animalCount,
                    'reason' => 'invalid_animal_count',
                ],
                event: 'inspection_request.blocked',
            );

            return $this->error(
                'Backyard micro-scale '.$category->name.' requires a declared animal count between 2 and 5.',
                422,
                ['declared_animal_count' => ['Backyard micro-scale '.$category->name.' requires a declared animal count between 2 and 5.']]
            );
        }

        return null;
    }
}
