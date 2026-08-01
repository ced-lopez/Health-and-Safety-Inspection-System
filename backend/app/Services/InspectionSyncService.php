<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\InspectionSchedule;

class InspectionSyncService
{
    public static function sync(InspectionSchedule $schedule): Inspection
    {
        return Inspection::query()->updateOrCreate(
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
