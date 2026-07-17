<?php

namespace App\Http\Controllers\Api;

class HealthController extends BaseApiController
{
    public function index()
    {
        return $this->success([
            'status' => 'ok',
            'service' => config('app.name'),
            'environment' => app()->environment(),
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ], 'API is running');
    }
}
