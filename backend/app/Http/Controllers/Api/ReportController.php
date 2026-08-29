<?php

namespace App\Http\Controllers\Api;

use App\Models\Clearance;
use App\Models\Inspection;
use App\Models\InspectionRequest;
use App\Models\InspectionResult;
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

    public function soba(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);
        $semester = $request->integer('semester', now()->month >= 7 ? 2 : 1);

        abort_unless(in_array($semester, [1, 2], true), 422, 'Semester must be 1 or 2.');

        $startMonth = $semester === 1 ? 1 : 7;
        $endMonth = $semester === 1 ? 6 : 12;

        $start = now()->create($year, $startMonth, 1)->startOfDay();
        $end = now()->create($year, $endMonth, 1)->endOfMonth()->endOfDay();

        $monthNumbers = range($startMonth, $endMonth);

        $monthlyRequests = $this->monthlyCounts(
            InspectionRequest::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw($this->monthExpression('created_at').' as month')
                ->selectRaw('COUNT(*) as total'),
            'created_at'
        );

        $monthlyInspections = $this->monthlyCounts(
            Inspection::query()
                ->whereBetween('inspection_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'completed')
                ->selectRaw($this->monthExpression('inspection_date').' as month')
                ->selectRaw('COUNT(*) as total'),
            'inspection_date'
        );

        $monthlyClearances = $this->monthlyCounts(
            Clearance::query()
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw($this->monthExpression('issue_date').' as month')
                ->selectRaw('COUNT(*) as total'),
            'issue_date'
        );

        $monthlyViolations = $this->monthlyCounts(
            Violation::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw($this->monthExpression('created_at').' as month')
                ->selectRaw('COUNT(*) as total'),
            'created_at'
        );

        $months = collect($monthNumbers)->map(fn ($month) => [
            'month' => $month,
            'requests' => $monthlyRequests->get($month, 0),
            'inspections' => $monthlyInspections->get($month, 0),
            'clearances' => $monthlyClearances->get($month, 0),
            'violations' => $monthlyViolations->get($month, 0),
        ]);

        $requestsByStatus = InspectionRequest::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $requestsByCategory = InspectionRequest::query()
            ->whereBetween('inspection_requests.created_at', [$start, $end])
            ->join('inspection_categories', 'inspection_categories.id', '=', 'inspection_requests.inspection_category_id')
            ->selectRaw('inspection_categories.name as category, COUNT(*) as total')
            ->groupBy('inspection_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total]);

        $violationsBySeverity = Violation::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $violationsByStatus = Violation::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $compliance = InspectionResult::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('compliance_status, COUNT(*) as total')
            ->groupBy('compliance_status')
            ->pluck('total', 'compliance_status');

        $complianceTotal = $compliance->sum();
        $compliant = (int) $compliance->get('compliant', 0);

        $clearancesByStatus = Clearance::query()
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

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
            'semester' => $semester,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => ($semester === 1 ? '1st' : '2nd').' Semester '.$year,
            ],
            'months' => $months,
            'requests' => [
                'total' => $months->sum('requests'),
                'by_status' => $requestsByStatus,
                'by_category' => $requestsByCategory,
            ],
            'inspections' => [
                'completed' => $months->sum('inspections'),
            ],
            'violations' => [
                'total' => $months->sum('violations'),
                'by_severity' => [
                    'minor' => (int) $violationsBySeverity->get('minor', 0),
                    'moderate' => (int) $violationsBySeverity->get('moderate', 0),
                    'major' => (int) $violationsBySeverity->get('major', 0),
                ],
                'by_status' => $violationsByStatus,
            ],
            'compliance' => [
                'total_checks' => $complianceTotal,
                'compliant' => $compliant,
                'non_compliant' => (int) $compliance->get('non_compliant', 0),
                'needs_correction' => (int) $compliance->get('needs_correction', 0),
                'compliance_rate' => $complianceTotal > 0 ? round(($compliant / $complianceTotal) * 100, 1) : 0,
            ],
            'clearances' => [
                'issued' => $months->sum('clearances'),
                'by_status' => $clearancesByStatus,
                'expiring_soon' => $expiring,
                'expired' => $expired,
            ],
        ], 'State of the Barangay Address (SOBA) report retrieved successfully');
    }

    private function monthlyCounts($baseQuery, string $dateColumn): \Illuminate\Support\Collection
    {
        $rows = $baseQuery
            ->groupByRaw($this->monthExpression($dateColumn))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return $rows->map(fn ($row) => (int) $row->total);
    }

    private function monthExpression(string $column): string
{
    return match (DB::connection()->getDriverName()) {
        'pgsql' => "EXTRACT(MONTH FROM {$column})",
        'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
        default => "MONTH({$column})", // mysql, mariadb
    };
}
}
