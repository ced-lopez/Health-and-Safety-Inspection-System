<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\InspectionReportResource;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use App\Services\InspectionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionReportController extends BaseApiController
{
    public function show(InspectionSchedule $inspectionSchedule): JsonResponse
    {
        return $this->success(
            new InspectionReportResource(self::loadReport($inspectionSchedule)),
            'Inspection report generated successfully'
        );
    }

    public function update(Request $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $validated = $request->validate([
            'overall_assessment' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $inspection = InspectionSyncService::sync($inspectionSchedule);
        $inspection->update($validated);
        $inspection->load([
            'establishment', 'inspector.role', 'schedule.establishment', 'schedule.inspector.role',
            'results.checklistItem.checklist', 'results.assessor.role', 'violations',
        ]);

        return $this->success(
            new InspectionReportResource($inspection),
            'Inspection report updated successfully'
        );
    }

    private static function loadReport(InspectionSchedule $schedule): Inspection
    {
        return InspectionSyncService::sync($schedule)
            ->load([
                'establishment',
                'inspector.role',
                'schedule.establishment',
                'schedule.inspector.role',
                'results.checklistItem.checklist',
                'results.assessor.role',
                'violations',
            ]);
    }
}
