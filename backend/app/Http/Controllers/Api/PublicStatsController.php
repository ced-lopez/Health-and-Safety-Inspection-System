<?php

namespace App\Http\Controllers\Api;

use App\Models\Clearance;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicStatsController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $residents = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'resident'))
            ->where('is_active', true)
            ->count();

        $inspections = Inspection::query()
            ->where('status', 'completed')
            ->count();

        $clearances = Clearance::query()
            ->where('status', 'active')
            ->count();

        return $this->success([
            'residents_registered' => $residents,
            'inspections_completed' => $inspections,
            'clearances_issued' => $clearances,
        ], 'Public landing stats retrieved');
    }
}
