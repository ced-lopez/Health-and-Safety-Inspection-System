<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\InspectionSchedule\StoreInspectionScheduleRequest;
use App\Http\Requests\InspectionSchedule\UpdateInspectionScheduleRequest;
use App\Http\Resources\EstablishmentResource;
use App\Http\Resources\InspectionRequestResource;
use App\Http\Resources\InspectionScheduleResource;
use App\Http\Resources\UserResource;
use App\Models\Establishment;
use App\Models\InspectionAssignment;
use App\Models\InspectionRequest;
use App\Models\InspectionSchedule;
use App\Models\User;
use App\Notifications\InspectionRescheduled;
use App\Notifications\InspectionScheduled;
use App\Services\AuditLogger;
use App\Services\InspectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InspectionScheduleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = InspectionSchedule::query()
            ->with(['establishment', 'inspector', 'request']);

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
            $search = '%'.strtolower($request->string('search')->trim()->toString()).'%';

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
        [$schedule, $wasScheduled] = $this->persistSchedule($request, $request->validated());

        return $this->success(
            new InspectionScheduleResource($schedule->load([
                'establishment', 'inspector.role', 'scheduler.role', 'request.inspectionCategory', 'request.resident',
            ])),
            $wasScheduled ? 'Inspection rescheduled successfully' : 'Inspection scheduled successfully',
            $wasScheduled ? 200 : 201
        );
    }

    public function confirmPreferred(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $validated = $request->validate([
            'inspector_id' => ['required', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
        ]);

        $payload = [
            'establishment_id' => $inspectionRequest->establishment_id,
            'inspector_id' => (int) $validated['inspector_id'],
            'inspection_request_id' => $inspectionRequest->id,
            'status' => 'scheduled',
            'schedule_type' => $inspectionRequest->status === 'follow_up_requested' ? 'follow_up' : 'initial',
        ];

        if (! empty($validated['scheduled_at'])) {
            $payload['scheduled_at'] = $validated['scheduled_at'];
        } elseif (! empty($validated['scheduled_date'])) {
            $payload['scheduled_date'] = $validated['scheduled_date'];
            $payload['scheduled_time'] = $validated['scheduled_time'] ?? null;
        } elseif ($inspectionRequest->preferred_schedule_at) {
            $payload['scheduled_at'] = $inspectionRequest->preferred_schedule_at;
        } else {
            return $this->error('Provide a date and time, or ask the resident to propose a preferred schedule first.', 422);
        }

        [$schedule] = $this->persistSchedule($request, $payload);

        if ($inspectionRequest->preferred_schedule_at) {
            $inspectionRequest->update(['preferred_schedule_at' => null]);
        }

        AuditLogger::log(
            $request->user(),
            'Inspection Requests',
            'Schedule Confirmed',
            "Confirmed schedule for request {$inspectionRequest->request_number}",
            $inspectionRequest,
            $request,
            newValues: [
                'inspector_id' => (int) $validated['inspector_id'],
                'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
            ],
            event: 'inspection_request.schedule_confirmed',
        );

        return $this->success([
            'schedule' => new InspectionScheduleResource($schedule->load([
                'establishment', 'inspector.role', 'scheduler.role', 'request.inspectionCategory', 'request.resident',
            ])),
            'request' => new InspectionRequestResource($inspectionRequest->fresh()->load([
                'resident.role', 'inspectionCategory', 'applicationType', 'establishment',
                'inspectionAssignment.inspector.role',
            ])),
        ], 'Preferred schedule confirmed successfully', 201);
    }

    private function persistSchedule(Request $request, array $payload): array
    {
        $payload['scheduled_by'] = $payload['scheduled_by'] ?? $request->user()->id;
        $payload = $this->normalizeScheduledAt($payload);
        $payload['schedule_type'] = $payload['schedule_type'] ?? 'initial';

        $existing = ! empty($payload['inspection_request_id'])
            ? InspectionSchedule::query()
                ->where('inspection_request_id', $payload['inspection_request_id'])
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->latest('id')
                ->first()
            : null;

        if ($existing) {
            $wasScheduled = $existing->scheduled_at !== null;
            $existing->update(array_merge($payload, [
                'server_version' => $existing->server_version + 1,
            ]));
            InspectionSyncService::sync($existing);
            $this->ensureAssignment($request, $existing);
            $this->notifyScheduleChange($existing, $wasScheduled);

            $this->logScheduleEvent(
                $request,
                $existing,
                $wasScheduled ? 'Rescheduled' : 'Scheduled',
                ($wasScheduled ? 'Rescheduled' : 'Scheduled').' inspection for '.($existing->establishment?->name ?? 'establishment'),
            );

            return [$existing, $wasScheduled];
        }

        $schedule = InspectionSchedule::query()->create($payload);
        InspectionSyncService::sync($schedule);
        $this->ensureAssignment($request, $schedule);
        $this->notifyScheduleChange($schedule, false);

        $this->logScheduleEvent(
            $request,
            $schedule,
            'Scheduled',
            'Scheduled inspection for '.($schedule->establishment?->name ?? 'establishment'),
        );

        return [$schedule, false];
    }

    public function show(InspectionSchedule $inspectionSchedule): JsonResponse
    {
        return $this->success(
            new InspectionScheduleResource($inspectionSchedule->load([
                'establishment', 'inspector.role', 'scheduler.role', 'request.inspectionCategory', 'request.resident',
            ])),
            'Inspection schedule details retrieved'
        );
    }

    public function update(UpdateInspectionScheduleRequest $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $payload = $this->normalizeScheduledAt($request->validated());
        $wasScheduled = $inspectionSchedule->scheduled_at !== null;

        $inspectionSchedule->update(array_merge($payload, [
            'server_version' => $inspectionSchedule->server_version + 1,
        ]));
        InspectionSyncService::sync($inspectionSchedule);

        $this->ensureAssignment($request, $inspectionSchedule);

        if ($inspectionSchedule->inspection_request_id) {
            $this->notifyScheduleChange($inspectionSchedule->load('request', 'inspector'), $wasScheduled);
        }

        $this->logScheduleEvent(
            $request,
            $inspectionSchedule,
            'Updated',
            'Updated inspection schedule for '.($inspectionSchedule->establishment?->name ?? 'establishment'),
        );

        return $this->success(
            new InspectionScheduleResource($inspectionSchedule->load([
                'establishment', 'inspector.role', 'scheduler.role', 'request.inspectionCategory', 'request.resident',
            ])),
            'Inspection schedule updated successfully'
        );
    }

    public function destroy(Request $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $inspectionSchedule->delete();
        $inspectionSchedule->inspections()->delete();

        AuditLogger::log(
            $request->user(),
            'Inspections',
            'Deleted',
            'Archived inspection schedule for '.($inspectionSchedule->establishment?->name ?? 'establishment')." (ID {$inspectionSchedule->id})",
            $inspectionSchedule,
            $request,
        );

        return $this->success(null, 'Inspection schedule archived successfully');
    }

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'inspector_id' => ['nullable', 'exists:users,id'],
            'category_id' => ['nullable', 'exists:inspection_categories,id'],
            'status' => ['nullable', 'in:all,scheduled,ongoing,completed,cancelled,follow_up,overdue'],
        ]);

        $from = ! empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->startOfDay()->subDays(7);
        $to = ! empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : $from->copy()->addMonths(2)->endOfDay();

        $query = InspectionSchedule::query()
            ->with([
                'establishment',
                'inspector.role',
                'request.inspectionCategory',
                'request.establishment',
                'request.resident',
            ])
            ->whereBetween('scheduled_at', [$from, $to]);

        if (! empty($validated['inspector_id'])) {
            $query->where('inspector_id', $validated['inspector_id']);
        }

        if (! empty($validated['category_id'])) {
            $query->whereHas('request', fn ($q) => $q->where('inspection_category_id', $validated['category_id']));
        }

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'follow_up') {
                $query->where('schedule_type', 'follow_up');
            } elseif ($validated['status'] === 'overdue') {
                $query->where('status', 'scheduled')->where('scheduled_at', '<', now());
            } else {
                $query->where('status', $validated['status']);
            }
        }

        $schedules = $query->orderBy('scheduled_at')->get();
        $conflictIds = $this->conflictingScheduleIds($schedules);

        $events = $schedules
            ->map(fn ($schedule) => array_merge($this->calendarEvent($schedule), [
                'conflict' => in_array($schedule->id, $conflictIds, true),
            ]))
            ->values();

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $events,
        ], 'Calendar data retrieved successfully');
    }

    public function schedule(Request $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'scheduled_at' => ['nullable', 'date'],
            'inspector_id' => ['nullable', 'exists:users,id'],
            'inspection_request_id' => ['nullable', 'exists:inspection_requests,id'],
            'schedule_type' => ['nullable', 'in:initial,follow_up'],
            'expected_version' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('expected_version', $validated)
            && (int) $validated['expected_version'] !== $inspectionSchedule->server_version) {
            return $this->error('This schedule was modified by someone else. Please refresh and try again.', 409);
        }

        $wasScheduled = $inspectionSchedule->scheduled_at !== null;
        $payload = [];

        if (! empty($validated['inspector_id'])) {
            $payload['inspector_id'] = (int) $validated['inspector_id'];
        }

        if (! empty($validated['inspection_request_id'])) {
            $payload['inspection_request_id'] = (int) $validated['inspection_request_id'];
        }

        if (! empty($validated['schedule_type'])) {
            $payload['schedule_type'] = $validated['schedule_type'];
        }

        $scheduledAt = $this->resolveScheduledAt($validated, $inspectionSchedule);

        if ($scheduledAt) {
            $payload['scheduled_at'] = $scheduledAt;
            $payload['scheduled_date'] = $scheduledAt->toDateString();
            $payload['scheduled_time'] = $scheduledAt->format('H:i');
        }

        DB::transaction(function () use ($inspectionSchedule, $payload) {
            $inspectionSchedule->update(array_merge($payload, [
                'server_version' => $inspectionSchedule->server_version + 1,
            ]));
            InspectionSyncService::sync($inspectionSchedule);
        });

        $inspectionSchedule->refresh()->load([
            'establishment', 'inspector.role', 'scheduler.role', 'request.inspectionCategory', 'request.resident',
        ]);

        $this->ensureAssignment($request, $inspectionSchedule);
        $this->notifyScheduleChange($inspectionSchedule, $wasScheduled);

        $this->logScheduleEvent(
            $request,
            $inspectionSchedule,
            $wasScheduled ? 'Rescheduled' : 'Scheduled',
            ($wasScheduled ? 'Rescheduled' : 'Scheduled').' inspection for '.($inspectionSchedule->establishment?->name ?? 'establishment'),
        );

        return $this->success([
            'schedule' => new InspectionScheduleResource($inspectionSchedule),
            'conflicts' => $this->conflictingSchedules($inspectionSchedule),
        ], $wasScheduled ? 'Inspection rescheduled successfully' : 'Inspection scheduled successfully');
    }

    public function availability(Request $request, User $inspector): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = ! empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->startOfDay();
        $to = ! empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : $from->copy()->addDays(30)->endOfDay();

        $bookings = InspectionSchedule::query()
            ->where('inspector_id', $inspector->id)
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->with(['establishment', 'request.inspectionCategory'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
                'title' => $schedule->request?->business_name ?: $schedule->establishment?->name,
                'category' => $schedule->request?->inspectionCategory?->name,
            ]);

        return $this->success([
            'inspector' => new UserResource($inspector),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'bookings' => $bookings,
        ], 'Inspector availability retrieved successfully');
    }

    private function ensureAssignment(Request $request, InspectionSchedule $schedule): void
    {
        if (! $schedule->inspection_request_id || ! $schedule->inspector_id) {
            return;
        }

        $inspectionRequest = InspectionRequest::query()->find($schedule->inspection_request_id);

        if (! $inspectionRequest) {
            return;
        }

        $active = InspectionAssignment::query()
            ->where('inspection_request_id', $inspectionRequest->id)
            ->whereIn('status', ['assigned', 'downloaded', 'in_progress'])
            ->first();

        if ($active) {
            return;
        }

        InspectionAssignment::query()->create([
            'inspection_request_id' => $inspectionRequest->id,
            'inspector_id' => $schedule->inspector_id,
            'assigned_by' => $schedule->scheduled_by ?? $request->user()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        if ($inspectionRequest->status === 'approved_for_inspection') {
            $inspectionRequest->update(['status' => 'assigned']);
        }
    }

    private function normalizeScheduledAt(array $payload): array
    {
        $scheduledAt = $this->combinedScheduledAt($payload);

        if ($scheduledAt) {
            $payload['scheduled_at'] = $scheduledAt;
            $payload['scheduled_date'] = $scheduledAt->toDateString();
            $payload['scheduled_time'] = $scheduledAt->format('H:i');
        }

        return $payload;
    }

    private function combinedScheduledAt(array $payload): ?Carbon
    {
        if (! empty($payload['scheduled_at'])) {
            return Carbon::parse($payload['scheduled_at']);
        }

        if (! empty($payload['scheduled_date'])) {
            $time = $payload['scheduled_time'] ?? '00:00';

            return Carbon::parse($payload['scheduled_date'].' '.$time);
        }

        return null;
    }

    private function resolveScheduledAt(array $validated, InspectionSchedule $schedule): ?Carbon
    {
        if (! empty($validated['scheduled_at'])) {
            return Carbon::parse($validated['scheduled_at']);
        }

        if (! empty($validated['scheduled_date'])) {
            $time = $validated['scheduled_time'] ?? $schedule->scheduled_time ?? '00:00';

            return Carbon::parse($validated['scheduled_date'].' '.$time);
        }

        return null;
    }

    private function conflictingSchedules(InspectionSchedule $schedule): array
    {
        if (! $schedule->scheduled_at) {
            return [];
        }

        $others = InspectionSchedule::query()
            ->where('inspector_id', $schedule->inspector_id)
            ->where('id', '!=', $schedule->id)
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', $schedule->scheduled_at->toDateString())
            ->with('establishment')
            ->get();

        return $others
            ->filter(fn ($other) => $this->overlaps($schedule, $other))
            ->map(fn ($other) => [
                'id' => $other->id,
                'title' => $other->establishment?->name,
                'scheduled_at' => $other->scheduled_at?->toIso8601String(),
                'inspector_id' => $other->inspector_id,
            ])
            ->values()
            ->all();
    }

    private function conflictingScheduleIds($schedules): array
    {
        $ids = [];

        foreach ($schedules as $schedule) {
            foreach ($schedules as $other) {
                if ($schedule->id === $other->id) {
                    continue;
                }

                if ($this->overlaps($schedule, $other)) {
                    $ids[$schedule->id] = true;
                    $ids[$other->id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private function overlaps(InspectionSchedule $a, InspectionSchedule $b): bool
    {
        if (! $a->scheduled_at || ! $b->scheduled_at) {
            return false;
        }

        if ($a->inspector_id !== $b->inspector_id) {
            return false;
        }

        if ($a->scheduled_at->toDateString() !== $b->scheduled_at->toDateString()) {
            return false;
        }

        return abs($a->scheduled_at->diffInMinutes($b->scheduled_at)) < 60;
    }

    private function calendarEvent(InspectionSchedule $schedule): array
    {
        $inspectionRequest = $schedule->request;

        return [
            'id' => $schedule->id,
            'title' => $inspectionRequest?->business_name ?: $inspectionRequest?->applicant_name ?: $schedule->establishment?->name,
            'establishment_id' => $schedule->establishment_id,
            'establishment_name' => $schedule->establishment?->name,
            'category' => $inspectionRequest?->inspectionCategory?->name ?? 'Inspection',
            'inspector_id' => $schedule->inspector_id,
            'inspector_name' => $schedule->inspector?->name,
            'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
            'scheduled_date' => $schedule->scheduled_at?->toDateString() ?? $schedule->scheduled_date?->toDateString(),
            'scheduled_time' => $schedule->scheduled_time,
            'status' => $schedule->status,
            'schedule_type' => $schedule->schedule_type,
            'request_id' => $inspectionRequest?->id,
            'request_number' => $inspectionRequest?->request_number,
            'request_status' => $inspectionRequest?->status,
            'assignment_id' => $schedule->inspection_assignment_id,
            'server_version' => $schedule->server_version,
        ];
    }

    private function notifyScheduleChange(InspectionSchedule $schedule, bool $wasScheduled): void
    {
        $this->linkToAssignment($schedule);

        $inspectionRequest = $schedule->request;

        if (! $inspectionRequest) {
            return;
        }

        $dateLabel = $schedule->scheduled_at?->format('M d, Y \a\t h:i A') ?? 'N/A';
        $resident = $inspectionRequest->resident;
        $inspector = $schedule->inspector;
        $businessName = $inspectionRequest->business_name ?: $inspectionRequest->applicant_name;

        if ($wasScheduled) {
            $resident?->notify(new InspectionRescheduled(
                $inspectionRequest->request_number,
                $inspectionRequest->applicant_name,
                $dateLabel,
                $inspector?->name,
            ));

            $inspector?->notify(new InspectionRescheduled(
                $inspectionRequest->request_number,
                $businessName,
                $dateLabel,
            ));
        } else {
            $resident?->notify(new InspectionScheduled(
                $inspectionRequest->request_number,
                $inspectionRequest->applicant_name,
                $dateLabel,
                $inspector?->name,
            ));

            $inspector?->notify(new InspectionScheduled(
                $inspectionRequest->request_number,
                $businessName,
                $dateLabel,
            ));
        }
    }

    private function linkToAssignment(InspectionSchedule $schedule): void
    {
        if (! $schedule->inspection_request_id) {
            return;
        }

        $inspectionRequest = $schedule->request;

        if (! $inspectionRequest) {
            return;
        }

        $assignment = $inspectionRequest->inspectionAssignment;

        if ($assignment && ! $schedule->inspection_assignment_id) {
            $schedule->update(['inspection_assignment_id' => $assignment->id]);
        }
    }

    private function logScheduleEvent(Request $request, InspectionSchedule $schedule, string $action, string $description): void
    {
        AuditLogger::log(
            $request->user(),
            'Inspections',
            $action,
            $description,
            $schedule,
            $request,
            newValues: [
                'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
                'inspector_id' => $schedule->inspector_id,
            ],
            event: $action === 'Scheduled' ? 'inspection.scheduled' : ($action === 'Rescheduled' ? 'inspection.rescheduled' : 'inspection.updated'),
        );
    }
}
