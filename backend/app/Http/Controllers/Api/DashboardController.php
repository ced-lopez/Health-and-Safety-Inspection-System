<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionAssignment;
use App\Models\InspectionRequest;
use App\Models\MobileSyncRecord;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role?->slug === 'resident') {
            return $this->success($this->residentData($user), 'Dashboard data retrieved successfully');
        }

        if ($user->role?->slug === 'inspector') {
            return $this->success($this->inspectorData($user), 'Dashboard data retrieved successfully');
        }

        $data = Cache::remember('dashboard.index', now()->addSeconds(30), function () {
            $pendingCount = InspectionRequest::query()
                ->whereIn('status', ['submitted', 'under_review'])
                ->count();

            $assignedInspectorCount = InspectionAssignment::query()
                ->whereIn('status', ['assigned', 'downloaded', 'in_progress'])
                ->distinct('inspector_id')
                ->count('inspector_id');

            $completedCount = Inspection::query()
                ->where('status', 'completed')
                ->whereYear('inspection_date', Carbon::now()->year)
                ->count();

            $activeViolationsCount = Violation::query()
                ->whereIn('status', ['open', 'under_review'])
                ->count();

            $expiringClearancesCount = Clearance::query()
                ->where('status', 'active')
                ->whereBetween('expiration_date', [Carbon::today(), Carbon::today()->addDays(30)])
                ->count();

            $activeEstablishmentsCount = Establishment::query()
                ->where('status', 'active')
                ->count();

            $monthlyInspections = Inspection::query()
                ->selectRaw($this->monthExpression('inspection_date').' as month')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
                ->whereYear('inspection_date', Carbon::now()->year)
                ->groupByRaw($this->monthExpression('inspection_date'))
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $monthlyClearances = Clearance::query()
                ->selectRaw($this->monthExpression('issue_date').' as month')
                ->selectRaw('COUNT(*) as issued')
                ->whereYear('issue_date', Carbon::now()->year)
                ->groupByRaw($this->monthExpression('issue_date'))
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $violationSeverity = Violation::query()
                ->selectRaw('severity, COUNT(*) as total')
                ->whereYear('created_at', Carbon::now()->year)
                ->groupBy('severity')
                ->pluck('total', 'severity');

            $violationStatus = Violation::query()
                ->selectRaw('status, COUNT(*) as total')
                ->whereYear('created_at', Carbon::now()->year)
                ->groupBy('status')
                ->pluck('total', 'status');

            $monthlySeries = collect(range(1, 12))->map(fn ($m) => [
                'month' => $m,
                'total' => (int) ($monthlyInspections->get($m)?->total ?? 0),
                'completed' => (int) ($monthlyInspections->get($m)?->completed ?? 0),
                'clearances' => (int) ($monthlyClearances->get($m)?->issued ?? 0),
            ])->toArray();

            $pendingRequests = InspectionRequest::query()
                ->whereIn('status', ['submitted', 'under_review'])
                ->with(['inspectionCategory', 'establishment'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($request) => [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'applicant_name' => $request->applicant_name,
                    'business_name' => $request->business_name,
                    'category' => $request->inspectionCategory?->name ?? 'Inspection',
                    'submitted_at' => $request->submitted_at?->format('M d, Y') ?? 'N/A',
                ])
                ->values()
                ->toArray();

            $assignedInspectors = InspectionAssignment::query()
                ->whereIn('status', ['assigned', 'downloaded', 'in_progress'])
                ->with(['inspector', 'inspectionRequest.inspectionCategory'])
                ->orderByDesc('assigned_at')
                ->limit(5)
                ->get()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'inspector_name' => $assignment->inspector?->name ?? 'Unassigned',
                    'request_number' => $assignment->inspectionRequest?->request_number ?? 'N/A',
                    'business_name' => $assignment->inspectionRequest?->business_name
                        ?: $assignment->inspectionRequest?->applicant_name,
                    'category' => $assignment->inspectionRequest?->inspectionCategory?->name ?? 'Inspection',
                    'status' => ucfirst(str_replace('_', ' ', $assignment->status)),
                    'assigned_at' => $assignment->assigned_at?->format('M d, Y') ?? 'N/A',
                ])
                ->values()
                ->toArray();

            $completedInspections = Inspection::query()
                ->where('status', 'completed')
                ->with(['establishment', 'inspector'])
                ->orderByDesc('inspection_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn ($inspection) => [
                    'id' => $inspection->id,
                    'establishment_name' => $inspection->establishment?->name ?? 'Unknown',
                    'inspector_name' => $inspection->inspector?->name ?? 'Unassigned',
                    'date' => $inspection->inspection_date?->format('M d, Y') ?? 'N/A',
                ])
                ->values()
                ->toArray();

            $activeViolations = Violation::query()
                ->whereIn('status', ['open', 'under_review'])
                ->with('establishment')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($violation) => [
                    'id' => $violation->id,
                    'title' => $violation->title,
                    'severity' => $violation->severity,
                    'status' => $violation->status,
                    'establishment_name' => $violation->establishment?->name ?? 'Unknown',
                    'correction_deadline' => $violation->correction_deadline?->format('M d, Y'),
                ])
                ->values()
                ->toArray();

            $expiringClearances = Clearance::query()
                ->where('status', 'active')
                ->whereBetween('expiration_date', [Carbon::today(), Carbon::today()->addDays(30)])
                ->with('establishment')
                ->orderBy('expiration_date')
                ->limit(5)
                ->get()
                ->map(fn ($clearance) => [
                    'id' => $clearance->id,
                    'clearance_number' => $clearance->clearance_number,
                    'clearance_type' => ucwords(str_replace('_', ' ', $clearance->clearance_type)),
                    'establishment_name' => $clearance->establishment?->name ?? 'Unknown',
                    'expiration_date' => $clearance->expiration_date?->format('M d, Y'),
                    'days_left' => $clearance->expiration_date
                        ? $clearance->expiration_date->diffInDays(Carbon::today())
                        : null,
                ])
                ->values()
                ->toArray();

            $recentActivities = AuditLog::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'module' => $log->module ?: $this->moduleFromEvent($log->event),
                    'action' => $log->action,
                    'description' => $log->description,
                    'user_name' => $log->user?->name ?? 'System',
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->values()
                ->toArray();

            // System-wide all-time totals
            $systemTotals = [
                'total_users' => User::query()->count(),
                'total_establishments' => Establishment::query()->count(),
                'total_requests' => InspectionRequest::query()->count(),
                'total_inspections' => Inspection::query()->count(),
                'total_violations' => Violation::query()->count(),
                'total_clearances' => Clearance::query()->count(),
            ];

            $recentUsers = User::query()
                ->with('role')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role?->name ?? $u->role?->slug ?? '—',
                    'role_slug' => $u->role?->slug ?? null,
                    'created_at' => $u->created_at?->toIso8601String(),
                    'is_active' => (bool) $u->is_active,
                ])
                ->values()
                ->toArray();

            $requestsByCategory = InspectionRequest::query()
                ->join('inspection_categories', 'inspection_categories.id', '=', 'inspection_requests.inspection_category_id')
                ->selectRaw('inspection_categories.name as category, inspection_categories.slug as slug, COUNT(*) as total')
                ->groupBy('inspection_categories.name', 'inspection_categories.slug')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['category' => $row->category, 'slug' => $row->slug, 'total' => (int) $row->total])
                ->toArray();

            $establishmentsByCategory = Establishment::query()
                ->selectRaw('COALESCE(NULLIF(category, \'\'), business_type, \'Uncategorized\') as category, COUNT(*) as total')
                ->groupByRaw('COALESCE(NULLIF(category, \'\'), business_type, \'Uncategorized\')')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total])
                ->toArray();

            return [
                'stats' => [
                    'pending_requests' => $pendingCount,
                    'assigned_inspectors' => $assignedInspectorCount,
                    'completed_inspections' => $completedCount,
                    'active_violations' => $activeViolationsCount,
                    'expiring_clearances' => $expiringClearancesCount,
                    'active_establishments' => $activeEstablishmentsCount,
                ],
                'system_totals' => $systemTotals,
                'recent_users' => $recentUsers,
                'requests_by_category' => $requestsByCategory,
                'establishments_by_category' => $establishmentsByCategory,
                'pending_requests' => $pendingRequests,
                'assigned_inspectors' => $assignedInspectors,
                'completed_inspections' => $completedInspections,
                'active_violations' => $activeViolations,
                'expiring_clearances' => $expiringClearances,
                'recent_activities' => $recentActivities,
                'chart' => [
                    'year' => Carbon::now()->year,
                    'monthly' => $monthlySeries,
                    'violations' => [
                        'by_severity' => [
                            'minor' => (int) ($violationSeverity['minor'] ?? 0),
                            'moderate' => (int) ($violationSeverity['moderate'] ?? 0),
                            'major' => (int) ($violationSeverity['major'] ?? 0),
                        ],
                        'by_status' => [
                            'open' => (int) ($violationStatus['open'] ?? 0),
                            'resolved' => (int) ($violationStatus['resolved'] ?? 0),
                        ],
                    ],
                ],
            ];
        });

        return $this->success($data, 'Dashboard data retrieved successfully');
    }

  private function monthExpression(string $column): string
{
    return match (DB::connection()->getDriverName()) {
        'pgsql' => "EXTRACT(MONTH FROM {$column})",
        'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
        default => "MONTH({$column})", // mysql, mariadb
    };
}

    private function moduleFromEvent(?string $event): string
    {
        if (! $event) {
            return 'System';
        }

        return ucwords(str_replace(['_', '.'], ' ', (string) preg_split('/[.\\/_]/', $event)[0]));
    }

    private function inspectorData(User $user): array
    {
        $activeStatuses = ['assigned', 'downloaded', 'in_progress'];
        $followUpStatuses = ['violation_notice_issued', 'follow_up_requested'];

        $assignedCount = InspectionAssignment::query()
            ->where('inspector_id', $user->id)
            ->whereIn('status', ['assigned', 'downloaded'])
            ->count();

        $inProgressCount = InspectionAssignment::query()
            ->where('inspector_id', $user->id)
            ->where('status', 'in_progress')
            ->count();

        $completedCount = Inspection::query()
            ->where('inspector_id', $user->id)
            ->where('status', 'completed')
            ->whereYear('inspection_date', Carbon::now()->year)
            ->count();

        $followUpCount = InspectionRequest::query()
            ->whereHas('inspectionAssignment', fn ($q) => $q->where('inspector_id', $user->id))
            ->whereIn('status', $followUpStatuses)
            ->count();

        $pendingSyncCount = MobileSyncRecord::query()
            ->where('inspector_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $assignedInspections = InspectionAssignment::query()
            ->where('inspector_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->with([
                'inspectionRequest.resident.role',
                'inspectionRequest.inspectionCategory',
                'inspectionRequest.applicationType',
                'inspectionRequest.establishment',
                'inspectionRequest.schedules',
            ])
            ->orderByDesc('assigned_at')
            ->get()
            ->map(fn ($assignment) => $this->assignmentCard($assignment))
            ->values();

        $inspectionHistory = Inspection::query()
            ->where('inspector_id', $user->id)
            ->with(['establishment', 'inspector.role'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($inspection) => [
                'id' => $inspection->id,
                'establishment_name' => $inspection->establishment?->name ?? 'Unknown',
                'business_type' => $inspection->establishment?->business_type,
                'date' => $inspection->inspection_date?->format('M d, Y') ?? 'N/A',
                'status' => ucfirst($inspection->status),
                'overall_assessment' => $inspection->overall_assessment,
            ])->values();

        $followUps = InspectionRequest::query()
            ->whereHas('inspectionAssignment', fn ($q) => $q->where('inspector_id', $user->id))
            ->whereIn('status', $followUpStatuses)
            ->with(['inspectionCategory', 'establishment', 'inspectionAssignment'])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(fn ($request) => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'business_name' => $request->business_name ?: $request->applicant_name,
                'category' => $request->inspectionCategory?->name ?? 'Inspection',
                'status' => $request->status,
                'reason' => $request->remarks,
                'assignment_id' => $request->inspectionAssignment?->id,
                'updated_at' => $request->updated_at?->format('M d, Y') ?? 'N/A',
            ])->values();

        $lastSync = MobileSyncRecord::query()
            ->where('inspector_id', $user->id)
            ->whereNotNull('processed_at')
            ->orderByDesc('processed_at')
            ->first();

        $unsyncedAssignments = InspectionAssignment::query()
            ->where('inspector_id', $user->id)
            ->where('status', 'downloaded')
            ->count();

        return [
            'stats' => [
                'assigned' => $assignedCount,
                'in_progress' => $inProgressCount,
                'completed' => $completedCount,
                'follow_ups' => $followUpCount,
                'pending_sync' => $pendingSyncCount,
            ],
            'assigned_inspections' => $assignedInspections,
            'inspection_history' => $inspectionHistory,
            'follow_ups' => $followUps,
            'sync' => [
                'last_synced_at' => $lastSync?->processed_at?->toIso8601String(),
                'pending_count' => $pendingSyncCount,
                'unsynced_assignments' => $unsyncedAssignments,
            ],
        ];
    }

    private function assignmentCard(InspectionAssignment $assignment): array
    {
        $request = $assignment->inspectionRequest;

        return [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at?->format('M d, Y'),
            'assigned_date' => $assignment->assigned_at?->toDateString(),
            'request_number' => $request?->request_number,
            'applicant_name' => $request?->applicant_name,
            'applicant_age' => $request?->applicant_age,
            'applicant_address' => $request?->applicant_address,
            'contact_number' => $request?->contact_number,
            'email' => $request?->email,
            'business_name' => $request?->business_name,
            'category' => $request?->inspectionCategory?->name ?? 'Inspection',
            'application_type' => $request?->applicationType?->name ?? 'New',
            'establishment_name' => $request?->establishment?->name,
            'establishment_address' => $request?->establishment?->address,
            'barangay' => $request?->establishment?->barangay,
            'business_type' => $request?->establishment?->business_type,
            'notes' => $assignment->notes,
            'scheduled_at' => $request?->schedules
                ?->sortByDesc('scheduled_at')
                ?->first()
                ?->scheduled_at?->format('M d, Y \a\t h:i A'),
        ];
    }

    private function residentData(User $user): array
    {
        $establishmentIds = Establishment::query()
            ->where('resident_id', $user->id)
            ->where('ownership_status', 'linked')
            ->pluck('id');

        $countByStatus = function (array $statuses) use ($user): int {
            return InspectionRequest::query()
                ->where('resident_id', $user->id)
                ->whereIn('status', $statuses)
                ->count();
        };

        $pendingStatuses = ['submitted', 'under_review', 'requirements_incomplete'];
        $inProgressStatuses = ['approved_for_inspection', 'assigned', 'violation_notice_issued', 'follow_up_requested'];
        $completedStatuses = ['inspection_completed', 'clearance_approved'];

        $activeApplications = InspectionRequest::query()
            ->where('resident_id', $user->id)
            ->whereIn('status', array_merge($pendingStatuses, $inProgressStatuses, ['inspection_completed']))
            ->with(['inspectionCategory', 'inspectionAssignment.inspector'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn ($request) => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'business_name' => $request->business_name ?: $request->applicant_name,
                'category' => $request->inspectionCategory?->name ?? 'Inspection',
                'status' => $request->status,
                'inspector_name' => $request->inspectionAssignment?->inspector?->name,
                'submitted_at' => $request->submitted_at?->format('M d, Y') ?? 'N/A',
            ])->values();

        $inspectionHistory = Inspection::query()
            ->whereIn('establishment_id', $establishmentIds)
            ->with(['establishment', 'inspector'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($inspection) => [
                'id' => $inspection->id,
                'establishment_name' => $inspection->establishment?->name ?? 'Unknown',
                'business_type' => $inspection->establishment?->business_type,
                'inspector_name' => $inspection->inspector?->name ?? 'Unassigned',
                'date' => $inspection->inspection_date?->format('M d, Y') ?? 'N/A',
                'status' => ucfirst($inspection->status),
            ])->values();

        $activeClearances = Clearance::query()
            ->where('status', 'active')
            ->whereIn('establishment_id', $establishmentIds)
            ->with(['establishment', 'qrCode'])
            ->orderByDesc('expiration_date')
            ->get()
            ->map(fn ($clearance) => [
                'id' => $clearance->id,
                'number' => $clearance->clearance_number,
                'type' => ucwords(str_replace('_', ' ', $clearance->clearance_type)),
                'establishment_name' => $clearance->establishment?->name ?? 'Unknown',
                'issue_date' => $clearance->issue_date?->format('M d, Y'),
                'expiration_date' => $clearance->expiration_date?->format('M d, Y'),
                'days_left' => $clearance->expiration_date
                    ? $clearance->expiration_date->diffInDays(Carbon::today())
                    : null,
                'qr_code' => $clearance->qrCode?->code,
            ])->values();

        $notifications = $user->notifications()
            ->limit(6)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return [
            'stats' => [
                'total_requests' => InspectionRequest::query()->where('resident_id', $user->id)->count(),
                'pending' => $countByStatus($pendingStatuses),
                'in_progress' => $countByStatus($inProgressStatuses),
                'completed' => $countByStatus($completedStatuses),
            ],
            'active_applications' => $activeApplications,
            'inspection_history' => $inspectionHistory,
            'active_clearances' => $activeClearances,
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }
}
