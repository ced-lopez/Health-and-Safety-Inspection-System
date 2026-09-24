<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\InspectionSchedule;

class InspectionSyncService
{
    public static function sync(InspectionSchedule $schedule): Inspection
    {
        $followUpOf = $schedule->schedule_type === 'follow_up' && $schedule->inspection_request_id
            ? Inspection::query()->where('inspection_request_id', $schedule->inspection_request_id)->where('status', 'completed')->latest('id')->value('id')
            : null;

        return Inspection::query()->updateOrCreate(
            ['inspection_schedule_id' => $schedule->id],
            [
                'establishment_id' => $schedule->establishment_id,
                'inspection_request_id' => $schedule->inspection_request_id,
                'follow_up_of_inspection_id' => $followUpOf,
                'inspector_id' => $schedule->inspector_id,
                'inspection_date' => $schedule->scheduled_date,
                'status' => $schedule->status,
            ]
        );
    }
}
