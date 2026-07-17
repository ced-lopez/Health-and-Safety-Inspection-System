<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Establishment\StoreEstablishmentRequest;
use App\Http\Requests\Establishment\UpdateEstablishmentRequest;
use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstablishmentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Establishment::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->string('search')->trim()->toString()) . '%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(owner_name) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(registration_number) LIKE ?', [$search]);
            });
        }

        // Filter by status (active, inactive, pending)
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by business type
        if ($request->filled('business_type') && $request->input('business_type') !== 'all') {
            $query->where('business_type', $request->input('business_type'));
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $establishments = $query->orderBy('name')->paginate($perPage);

        // Fetch distinct business types currently in database to feed the filter dropdown dynamically
        $businessTypes = Establishment::query()
            ->whereNotNull('business_type')
            ->distinct()
            ->orderBy('business_type')
            ->pluck('business_type');

        return $this->success([
            'establishments' => EstablishmentResource::collection($establishments),
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
        $establishment = Establishment::query()->create($request->validated());

        return $this->success(
            new EstablishmentResource($establishment),
            'Establishment registered successfully',
            201
        );
    }

    public function show(Establishment $establishment): JsonResponse
    {
        return $this->success(
            new EstablishmentResource($establishment),
            'Establishment details retrieved'
        );
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): JsonResponse
    {
        $establishment->update($request->validated());

        return $this->success(
            new EstablishmentResource($establishment),
            'Establishment details updated successfully'
        );
    }

    public function destroy(Establishment $establishment): JsonResponse
    {
        $establishment->delete();

        return $this->success(
            null,
            'Establishment archived successfully'
        );
    }
}
