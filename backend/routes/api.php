<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health & Safety Inspections System — API Routes
|--------------------------------------------------------------------------
|
| Base URL: /api/v1
| Authentication: Laravel Sanctum (Bearer token)
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/verify/{code}', [\App\Http\Controllers\Api\CertificationController::class, 'verify']);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'index']);

        Route::prefix('establishments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\EstablishmentController::class, 'index'])
                ->middleware('role:administrator,health_officer,inspector');
            Route::get('/{establishment}', [\App\Http\Controllers\Api\EstablishmentController::class, 'show'])
                ->middleware('role:administrator,health_officer,inspector');
            Route::post('/', [\App\Http\Controllers\Api\EstablishmentController::class, 'store'])
                ->middleware('role:administrator,health_officer');
            Route::put('/{establishment}', [\App\Http\Controllers\Api\EstablishmentController::class, 'update'])
                ->middleware('role:administrator,health_officer');
            Route::delete('/{establishment}', [\App\Http\Controllers\Api\EstablishmentController::class, 'destroy'])
                ->middleware('role:administrator');
        });

        Route::prefix('inspections')->middleware('role:administrator,health_officer,inspector')->group(function () {
            Route::get('/options', [\App\Http\Controllers\Api\InspectionScheduleController::class, 'options']);
            Route::get('/schedules', [\App\Http\Controllers\Api\InspectionScheduleController::class, 'index']);
            Route::get('/schedules/{inspectionSchedule}', [\App\Http\Controllers\Api\InspectionScheduleController::class, 'show']);
            Route::post('/schedules', [\App\Http\Controllers\Api\InspectionScheduleController::class, 'store'])
                ->middleware('role:administrator,health_officer');
            Route::put('/schedules/{inspectionSchedule}', [\App\Http\Controllers\Api\InspectionScheduleController::class, 'update'])
                ->middleware('role:administrator,health_officer');
            Route::delete('/schedules/{inspectionSchedule}', [\App\Http\Controllers\Api\InspectionScheduleController::class, 'destroy'])
                ->middleware('role:administrator');
            Route::get('/schedules/{inspectionSchedule}/checklist', [\App\Http\Controllers\Api\ComplianceChecklistController::class, 'show']);
            Route::post('/schedules/{inspectionSchedule}/checklist', [\App\Http\Controllers\Api\ComplianceChecklistController::class, 'store']);
            Route::get('/schedules/{inspectionSchedule}/report', [\App\Http\Controllers\Api\InspectionReportController::class, 'show']);
            Route::put('/schedules/{inspectionSchedule}/report', [\App\Http\Controllers\Api\InspectionReportController::class, 'update']);
        });

        Route::prefix('violations')->middleware('role:administrator,health_officer,inspector')->group(function () {
            Route::get('/options', [\App\Http\Controllers\Api\ViolationController::class, 'options']);
            Route::get('/', [\App\Http\Controllers\Api\ViolationController::class, 'index']);
            Route::get('/{violation}', [\App\Http\Controllers\Api\ViolationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\ViolationController::class, 'store']);
            Route::put('/{violation}', [\App\Http\Controllers\Api\ViolationController::class, 'update']);
            Route::delete('/{violation}', [\App\Http\Controllers\Api\ViolationController::class, 'destroy'])
                ->middleware('role:administrator,health_officer');
            Route::post('/{violation}/evidence', [\App\Http\Controllers\Api\ViolationController::class, 'storeEvidence']);
        });

        Route::prefix('certifications')->middleware('role:administrator,health_officer')->group(function () {
            Route::get('/options', [\App\Http\Controllers\Api\CertificationController::class, 'options']);
            Route::get('/', [\App\Http\Controllers\Api\CertificationController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\CertificationController::class, 'store']);
            Route::put('/{kind}/{id}', [\App\Http\Controllers\Api\CertificationController::class, 'update']);
            Route::delete('/{kind}/{id}', [\App\Http\Controllers\Api\CertificationController::class, 'destroy'])
                ->middleware('role:administrator');
        });

        Route::prefix('documents')->middleware('role:administrator,health_officer')->group(function () {
            // Phase 12: AI document processing
        });
    });
});
