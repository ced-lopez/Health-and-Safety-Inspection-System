<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\InspectionReportResource;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionReportController extends BaseApiController
{
    public function show(InspectionSchedule $inspectionSchedule): JsonResponse
    {
        return $this->success(
            new InspectionReportResource($this->reportInspection($inspectionSchedule)),
            'Inspection report generated successfully'
        );
    }

    public function update(Request $request, InspectionSchedule $inspectionSchedule): JsonResponse
    {
        $validated = $request->validate([
            'overall_assessment' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $inspection = $this->reportInspection($inspectionSchedule);
        $inspection->update($validated);

        return $this->success(
            new InspectionReportResource($this->reportInspection($inspectionSchedule)),
            'Inspection report updated successfully'
        );
    }

    private function reportInspection(InspectionSchedule $schedule): Inspection
    {
        return Inspection::query()
            ->firstOrCreate(
                ['inspection_schedule_id' => $schedule->id],
                [
                    'establishment_id' => $schedule->establishment_id,
                    'inspector_id' => $schedule->inspector_id,
                    'inspection_date' => $schedule->scheduled_date,
                    'status' => $schedule->status,
                ]
            )
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
