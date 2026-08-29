<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Establishment\StoreEstablishmentRequest;
use App\Http\Requests\Establishment\UpdateEstablishmentRequest;
use App\Http\Resources\CertificationResource;
use App\Http\Resources\ClearanceResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\EstablishmentResource;
use App\Http\Resources\InspectionResource;
use App\Http\Resources\InspectionRequestResource;
use App\Http\Resources\InspectionScheduleResource;
use App\Http\Resources\ViolationResource;
use App\Models\Establishment;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstablishmentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Establishment::query()
            ->withCount('inspections')
            ->withCount('openViolations as open_violations_count')
            ->withMax('inspections', 'inspection_date');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->trim()->toString()).'%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(owner_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(registration_number) LIKE ?', [$search]);
            });
        }

        // Filter by address / location.
        if ($request->filled('address')) {
            $address = '%'.strtolower($request->string('address')->trim()->toString()).'%';

            $query->where(function ($q) use ($address) {
                $q->whereRaw('LOWER(address) LIKE ?', [$address])
                    ->orWhereRaw('LOWER(barangay) LIKE ?', [$address]);
            });
        }

        // Filter by status (active, inactive, pending)
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by establishment category
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        // Filter by business type
        if ($request->filled('business_type') && $request->input('business_type') !== 'all') {
            $query->where('business_type', $request->input('business_type'));
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $establishments = $query->orderBy('name')->paginate($perPage);

        // Distinct business types in the database for the filter dropdown.
        $businessTypes = Establishment::query()
            ->whereNotNull('business_type')
            ->distinct()
            ->orderBy('business_type')
            ->pluck('business_type');

        return $this->success([
            'establishments' => EstablishmentResource::collection($establishments),
            'categories' => EstablishmentResource::CATEGORY_LABELS,
            'business_types' => $businessTypes,
            'meta' => [
                'current_page' => $establishments->currentPage(),
                'last_page' => $establishments->lastPage(),
                'per_page' => $establishments->perPage(),
                'total' => $establishments->total(),
            ],
        ], 'Establishments listed successfully');
    }

    public function store(StoreEstablishmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['category'] ??= 'food_establishment';

        $establishment = Establishment::query()->create($validated);

        AuditLogger::log(
            $request->user(),
            'Establishments',
            'Created',
            "Registered establishment {$establishment->name}",
            $establishment,
            $request,
            newValues: [
                'name' => $establishment->name,
                'category' => $establishment->category,
                'registration_number' => $establishment->registration_number,
                'status' => $establishment->status,
            ],
        );

        return $this->success(
            new EstablishmentResource($establishment),
            'Establishment registered successfully',
            201
        );
    }

    public function show(Establishment $establishment): JsonResponse
    {
        $establishment->load([
            'resident',
            'inspectionSchedules.inspector.role',
            'inspections.inspector.role',
            'inspections.results',
            'violations.reportedBy.role',
            'violations.resolvedBy.role',
            'violations.inspection',
            'documents.uploader.role',
            'certifications.inspection',
            'clearances.inspection',
            'inspectionRequests.inspectionCategory',
            'inspectionRequests.applicationType',
        ]);

        $summary = [
            'inspections_count' => $establishment->inspections()->count(),
            'open_violations_count' => $establishment->openViolations()->count(),
            'documents_count' => $establishment->documents()->count(),
            'certifications_count' => $establishment->certifications()->count(),
            'clearances_count' => $establishment->clearances()->count(),
            'last_inspection_date' => $establishment->inspections()->max('inspection_date'),
        ];

        // Compose a lightweight activity timeline from linked records.
        $activity = collect()
            ->merge(
                $establishment->inspections
                    ->map(fn ($inspection) => [
                        'type' => 'inspection',
                        'label' => 'Inspection '.ucfirst($inspection->status),
                        'detail' => 'Inspected by '.($inspection->inspector?->name ?? 'unassigned'),
                        'at' => $inspection->inspection_date?->toIso8601String(),
                    ])
            )
            ->merge(
                $establishment->documents
                    ->map(fn ($document) => [
                        'type' => 'document',
                        'label' => 'Document uploaded',
                        'detail' => $document->original_name,
                        'at' => $document->created_at?->toIso8601String(),
                    ])
            )
            ->merge(
                $establishment->inspectionRequests
                    ->map(fn ($request) => [
                        'type' => 'request',
                        'label' => 'Inspection request '.ucfirst(str_replace('_', ' ', $request->status)),
                        'detail' => $request->request_number,
                        'at' => $request->submitted_at?->toIso8601String() ?? $request->created_at?->toIso8601String(),
                    ])
            )
            ->filter(fn ($entry) => $entry['at'] !== null)
            ->sortByDesc('at')
            ->take(20)
            ->values();

        return $this->success([
            'establishment' => new EstablishmentResource($establishment),
            'summary' => $summary,
            'inspections' => InspectionResource::collection($establishment->inspections),
            'inspection_schedules' => InspectionScheduleResource::collection($establishment->inspectionSchedules),
            'violations' => ViolationResource::collection($establishment->violations),
            'documents' => DocumentResource::collection($establishment->documents),
            'certifications' => CertificationResource::collection($establishment->certifications),
            'clearances' => ClearanceResource::collection($establishment->clearances),
            'follow_ups' => InspectionRequestResource::collection(
                $establishment->inspectionRequests->whereIn('status', ['violation_notice_issued', 'follow_up_requested', 'clearance_approved'])
            ),
            'activity' => $activity,
        ], 'Establishment profile retrieved successfully');
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): JsonResponse
    {
        $old = [
            'name' => $establishment->name,
            'status' => $establishment->status,
            'category' => $establishment->category,
            'business_type' => $establishment->business_type,
        ];

        $validated = $request->validated();
        $validated['category'] ??= $establishment->category ?? 'food_establishment';

        $establishment->update($validated);

        AuditLogger::log(
            $request->user(),
            'Establishments',
            'Updated',
            "Updated establishment {$establishment->name}",
            $establishment,
            $request,
            oldValues: $old,
            newValues: [
                'name' => $establishment->name,
                'status' => $establishment->status,
                'category' => $establishment->category,
                'business_type' => $establishment->business_type,
            ],
        );

        return $this->success(
            new EstablishmentResource($establishment),
            'Establishment details updated successfully'
        );
    }

    public function destroy(Establishment $establishment): JsonResponse
    {
        $establishment->delete();

        AuditLogger::log(
            request()->user(),
            'Establishments',
            'Deleted',
            "Archived establishment {$establishment->name} (ID {$establishment->id})",
            $establishment,
            request(),
            newValues: ['deleted_at' => now()->toDateTimeString()],
        );

        return $this->success(
            null,
            'Establishment archived successfully'
        );
    }
}