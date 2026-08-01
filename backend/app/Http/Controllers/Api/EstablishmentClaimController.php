<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstablishmentClaimController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $establishments = Establishment::query()
            ->with('resident')
            ->where('ownership_status', 'pending')
            ->orderBy('updated_at')
            ->get();

        return $this->success(
            EstablishmentResource::collection($establishments),
            'Pending establishment claims retrieved successfully'
        );
    }

    public function approve(Request $request, Establishment $establishment): JsonResponse
    {
        if ($establishment->ownership_status !== 'pending' || $establishment->resident_id === null) {
            return $this->error('This establishment has no pending claim to approve.', 422);
        }

        $establishment->update(['ownership_status' => 'linked']);

        $this->logClaimEvent($establishment, 'establishment_claim.approved', $request);

        return $this->success(
            new EstablishmentResource($establishment->fresh()->load('resident')),
            'Establishment claim approved'
        );
    }

    public function reject(Request $request, Establishment $establishment): JsonResponse
    {
        if ($establishment->ownership_status !== 'pending' || $establishment->resident_id === null) {
            return $this->error('This establishment has no pending claim to reject.', 422);
        }

        $establishment->update([
            'resident_id' => null,
            'ownership_status' => 'unclaimed',
        ]);

        $this->logClaimEvent($establishment, 'establishment_claim.rejected', $request);

        return $this->success(
            new EstablishmentResource($establishment->fresh()->load('resident')),
            'Establishment claim rejected'
        );
    }

    private function logClaimEvent(Establishment $establishment, string $event, Request $request): void
    {
        $approved = $event === 'establishment_claim.approved';

        AuditLogger::log(
            $request->user(),
            'Establishments',
            $approved ? 'Claim Approved' : 'Claim Rejected',
            ($approved ? 'Approved' : 'Rejected')." ownership claim for establishment {$establishment->name}",
            $establishment,
            $request,
            newValues: [
                'resident_id' => $establishment->resident_id,
                'ownership_status' => $establishment->ownership_status,
            ],
            event: $event,
        );
    }
}
