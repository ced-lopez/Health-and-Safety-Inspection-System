<?php

namespace App\Http\Controllers\Api;

use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        // 1. Upcoming scheduled checks from the scheduling source of truth
        $scheduledCount = InspectionSchedule::query()
            ->where('status', 'scheduled')
            ->whereDate('scheduled_date', '>=', Carbon::today())
            ->count();

        // 2. Active Establishments
        $activeEstablishmentsCount = Establishment::query()
            ->where('status', 'active')
            ->count();

        // 3. Open Violations
        $openViolationsCount = Violation::query()
            ->where('status', 'open')
            ->count();

        // 4. Completed Inspections YTD (Year-to-date)
        $completedCount = Inspection::query()
            ->where('status', 'completed')
            ->whereYear('inspection_date', Carbon::now()->year)
            ->count();

        // 5. Recent Inspection activities (fetch latest 5)
        $recentInspections = Inspection::query()
            ->with(['establishment', 'inspector'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($inspection) {
                return [
                    'id' => $inspection->id,
                    'establishment_name' => $inspection->establishment?->name ?? 'Unknown',
                    'inspector_name' => $inspection->inspector?->name ?? 'Unassigned',
                    'date' => $inspection->inspection_date?->format('M d, Y') ?? 'N/A',
                    'status' => ucfirst($inspection->status),
                ];
            });

        return $this->success([
            'stats' => [
                'scheduled_inspections' => $scheduledCount,
                'active_establishments' => $activeEstablishmentsCount,
                'open_violations' => $openViolationsCount,
                'completed_inspections' => $completedCount,
            ],
            'recent_inspections' => $recentInspections,
        ], 'Dashboard data retrieved successfully');
    }
}
