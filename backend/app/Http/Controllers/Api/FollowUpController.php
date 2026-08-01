<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\InspectionRequestResource;
use App\Models\InspectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowUpController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = InspectionRequest::query()
            ->with(['resident.role', 'inspectionCategory', 'applicationType', 'establishment', 'reviewedBy'])
            ->whereIn('status', ['violation_notice_issued', 'follow_up_requested', 'clearance_approved']);

        if ($user->role?->slug === 'resident') {
            $query->where('resident_id', $user->id);
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $requests = $query->orderByDesc('updated_at')->paginate($perPage);

        return $this->success([
            'follow_ups' => InspectionRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 'Follow-up requests retrieved successfully');
    }

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inspection_request_id' => ['required', 'exists:inspection_requests,id'],
            'violation_id' => ['nullable', 'exists:violations,id'],
            'compliance_notes' => ['nullable', 'string'],
        ]);

        $inspectionRequest = InspectionRequest::query()->findOrFail($validated['inspection_request_id']);

        if ($inspectionRequest->resident_id !== $request->user()->id) {
            return $this->error('You can only request follow-up on your own applications', 403);
        }

        if ($inspectionRequest->status !== 'violation_notice_issued') {
            return $this->error('Follow-up can only be requested for requests with active violation notices', 422);
        }

        $inspectionRequest->update([
            'status' => 'follow_up_requested',
            'remarks' => $validated['compliance_notes']
                ? ($inspectionRequest->remarks ? $inspectionRequest->remarks."\n---\n".$validated['compliance_notes'] : $validated['compliance_notes'])
                : $inspectionRequest->remarks,
        ]);

        return $this->success(
            new InspectionRequestResource($inspectionRequest->load([
                'resident.role', 'inspectionCategory', 'applicationType', 'establishment',
            ])),
            'Follow-up inspection requested successfully',
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
        ]);

        return $this->success(
            new InspectionRequestResource($inspectionRequest),
            'Follow-up details retrieved successfully'
        );
    }
}
