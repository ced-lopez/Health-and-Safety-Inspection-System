<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\InspectionSchedule\StoreInspectionScheduleRequest;
use App\Http\Requests\InspectionSchedule\UpdateInspectionScheduleRequest;
use App\Http\Resources\EstablishmentResource;
use App\Http\Resources\InspectionScheduleResource;
use App\Http\Resources\UserResource;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionScheduleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = InspectionSchedule::query()
            ->with(['establishment', 'inspector']);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('inspector_id') && $request->input('inspector_id') !== 'all') {
            $query->where('inspector_id', $request->integer('inspector_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_date', $request->date('date'));
        }

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->string('search')->trim()->toString()) . '%';

            $query->whereHas('establishment', function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(registration_number) LIKE ?', [$search]);
            });
        }

        $perPage = min($request->integer('per_page', 10), 50);
        $schedules = $query
            ->orderByDesc('scheduled_date')
            ->orderBy('scheduled_time')
            ->paginate($perPage);

        return $this->success([
            'schedules' => InspectionScheduleResource::collection($schedules),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
        ], 'Inspection schedules listed successfully');
    }

    public function options(): JsonResponse
    {
        $establishments = Establishment::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $inspectors = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', 'inspector'))
            ->with('role')
            ->orderBy('name')
            ->get();

        return $this->success([
            'establishments' => EstablishmentResource::collection($establishments),
            'inspectors' => UserResource::collection($inspectors),
        ], 'Inspection scheduling options retrieved');
    }

    public function store(StoreInspectionScheduleRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['scheduled_by'] = $request->user()->id;

        $schedule = InspectionSchedule::query()->create($payload);
        $this->syncInspection($schedule);

        return $this->success(
            new InspectionScheduleResource($schedule->load(['establishment', 'inspector.role', 'scheduler.role'])),
            'Inspection scheduled successfully',
            201
        );
    }

    public function show(InspectionSchedule $inspectionSchedule): JsonResponse
    {
        return $this->success(
            new InspectionScheduleResource($inspectionSchedule->load(['establishment', 'inspector.role', 'scheduler.role'])),
            'Inspection schedule details retrieved'
        );
    }

    public function update(UpdateInspectionScheduleRequest $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $inspectionSchedule->update($request->validated());
        $this->syncInspection($inspectionSchedule);

        return $this->success(
            new InspectionScheduleResource($inspectionSchedule->load(['establishment', 'inspector.role', 'scheduler.role'])),
            'Inspection schedule updated successfully'
        );
    }

    public function destroy(InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $inspectionSchedule->delete();
        $inspectionSchedule->inspections()->delete();

        return $this->success(null, 'Inspection schedule archived successfully');
    }

    private function syncInspection(InspectionSchedule $schedule): void
    {
        Inspection::query()->updateOrCreate(
            ['inspection_schedule_id' => $schedule->id],
            [
                'establishment_id' => $schedule->establishment_id,
                'inspector_id' => $schedule->inspector_id,
                'inspection_date' => $schedule->scheduled_date,
                'status' => $schedule->status,
            ]
        );
    }
}
