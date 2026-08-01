<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyEstablishmentController extends BaseApiController
{
    public function mine(Request $request): JsonResponse
    {
        $establishments = Establishment::query()
            ->with('resident')
            ->where('resident_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return $this->success(
            EstablishmentResource::collection($establishments),
            'My establishments retrieved successfully'
        );
    }

    public function unclaimed(Request $request): JsonResponse
    {
        $establishments = Establishment::query()
            ->where('ownership_status', 'unclaimed')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->trim()->toString().'%')
            )
            ->orderBy('name')
            ->limit(50)
            ->get();

        return $this->success(
            EstablishmentResource::collection($establishments),
            'Unclaimed establishments retrieved successfully'
        );
    }

    public function claim(Establishment $establishment): JsonResponse
    {
        $claimed = Establishment::query()
            ->where('id', $establishment->id)
            ->where('ownership_status', 'unclaimed')
            ->update([
                'resident_id' => auth()->id(),
                'ownership_status' => 'pending',
            ]);

        if ($claimed !== 1) {
            return $this->error('This establishment is already claimed or pending review.', 422);
        }

        return $this->success(
            new EstablishmentResource($establishment->fresh()->load('resident')),
            'Establishment claim submitted. A barangay staff will review your request.',
            202
        );
    }
}
