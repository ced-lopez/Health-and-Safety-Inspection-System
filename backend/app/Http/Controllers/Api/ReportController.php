<?php

namespace App\Http\Controllers\Api;

use App\Models\Clearance;
use App\Models\Inspection;
use App\Models\InspectionRequest;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseApiController
{
    public function inspections(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $monthly = Inspection::query()
            ->selectRaw($this->monthExpression('inspection_date').' as month')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->whereYear('inspection_date', $year)
            ->groupByRaw($this->monthExpression('inspection_date'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'total' => (int) ($monthly->get($m)?->total ?? 0),
            'completed' => (int) ($monthly->get($m)?->completed ?? 0),
        ]);

        return $this->success([
            'year' => $year,
            'monthly' => $months,
            'total_inspections' => $months->sum('total'),
            'total_completed' => $months->sum('completed'),
        ], 'Inspection report retrieved successfully');
    }

    public function violations(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $bySeverity = Violation::query()
            ->selectRaw('severity, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $byStatus = Violation::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->success([
            'year' => $year,
            'by_severity' => [
                'minor' => (int) ($bySeverity->get('minor', 0)),
                'moderate' => (int) ($bySeverity->get('moderate', 0)),
                'major' => (int) ($bySeverity->get('major', 0)),
            ],
            'by_status' => [
                'open' => (int) ($byStatus->get('open', 0)),
                'resolved' => (int) ($byStatus->get('resolved', 0)),
            ],
            'total' => $bySeverity->sum(),
        ], 'Violation report retrieved successfully');
    }

    public function clearances(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $monthly = Clearance::query()
            ->selectRaw($this->monthExpression('issue_date').' as month')
            ->selectRaw('COUNT(*) as issued')
            ->whereYear('issue_date', $year)
            ->groupByRaw($this->monthExpression('issue_date'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'issued' => (int) ($monthly->get($m)?->issued ?? 0),
        ]);

        $expiring = Clearance::query()
            ->where('status', 'active')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->count();

        $expired = Clearance::query()
            ->where('status', 'active')
            ->where('expiration_date', '<', now())
            ->count();

        return $this->success([
            'year' => $year,
            'monthly' => $months,
            'total_issued' => $months->sum('issued'),
            'expiring_soon' => $expiring,
            'expired' => $expired,
        ], 'Clearance report retrieved successfully');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $pendingRequests = InspectionRequest::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->count();

        $activeViolations = Violation::query()
            ->whereIn('status', ['open', 'under_review'])
            ->count();

        $completedInspections = Inspection::query()
            ->where('status', 'completed')
            ->whereYear('inspection_date', now()->year)
            ->count();

        $expiringClearances = Clearance::query()
            ->where('status', 'active')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->count();

        $recentRequests = InspectionRequest::query()
            ->with(['inspectionCategory', 'resident.role'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'request_number' => $r->request_number,
                'applicant_name' => $r->applicant_name,
                'category' => $r->inspectionCategory?->name,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return $this->success([
            'pending_requests' => $pendingRequests,
            'active_violations' => $activeViolations,
            'completed_inspections_ytd' => $completedInspections,
            'expiring_clearances' => $expiringClearances,
            'recent_requests' => $recentRequests,
        ], 'Dashboard analytics retrieved successfully');
    }

    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "EXTRACT(MONTH FROM {$column})"
            : "CAST(strftime('%m', {$column}) AS INTEGER)";
    }
}
