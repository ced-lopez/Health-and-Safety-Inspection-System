<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\CertificationController;
use App\Http\Controllers\Api\ChecklistTemplateController;
use App\Http\Controllers\Api\ComplianceChecklistController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EstablishmentClaimController;
use App\Http\Controllers\Api\EstablishmentController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InspectionAssignmentController;
use App\Http\Controllers\Api\InspectionReportController;
use App\Http\Controllers\Api\InspectionRequestController;
use App\Http\Controllers\Api\InspectionScheduleController;
use App\Http\Controllers\Api\MyClearanceController;
use App\Http\Controllers\Api\MyEstablishmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OcrResultController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ViolationController;
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
    Route::get('/verify/{code}', [CertificationController::class, 'verify']);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:6,1');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');

    Route::middleware(['idle', 'auth:sanctum'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'changePassword']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::prefix('establishments')->group(function () {
            Route::get('/', [EstablishmentController::class, 'index'])
                ->middleware('role:administrator,barangay_staff,inspector');
            Route::get('/{establishment}', [EstablishmentController::class, 'show'])
                ->middleware('role:administrator,barangay_staff,inspector');
            Route::post('/', [EstablishmentController::class, 'store'])
                ->middleware('role:administrator,barangay_staff');
            Route::put('/{establishment}', [EstablishmentController::class, 'update'])
                ->middleware('role:administrator,barangay_staff');
            Route::delete('/{establishment}', [EstablishmentController::class, 'destroy'])
                ->middleware('role:administrator');
        });

        Route::prefix('my')->group(function () {
            Route::get('/clearances', [MyClearanceController::class, 'index']);
            Route::get('/clearances/{clearance}/pdf', [MyClearanceController::class, 'pdf']);
            Route::get('/clearances/{clearance}/qr-card', [MyClearanceController::class, 'qrCard']);
            Route::get('/establishments', [MyEstablishmentController::class, 'mine']);
            Route::get('/establishments/unclaimed', [MyEstablishmentController::class, 'unclaimed']);
            Route::post('/establishments/{establishment}/claim', [MyEstablishmentController::class, 'claim']);
        });

        Route::prefix('establishment-claims')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/', [EstablishmentClaimController::class, 'index']);
            Route::post('/{establishment}/approve', [EstablishmentClaimController::class, 'approve']);
            Route::post('/{establishment}/reject', [EstablishmentClaimController::class, 'reject']);
        });

        Route::prefix('inspections')->middleware('role:administrator,barangay_staff,inspector')->group(function () {
            Route::get('/options', [InspectionScheduleController::class, 'options']);
            Route::get('/calendar', [InspectionScheduleController::class, 'calendar'])
                ->middleware('role:administrator,barangay_staff');
            Route::get('/schedules', [InspectionScheduleController::class, 'index']);
            Route::get('/schedules/{inspectionSchedule}', [InspectionScheduleController::class, 'show']);
            Route::post('/schedules', [InspectionScheduleController::class, 'store'])
                ->middleware('role:administrator,barangay_staff');
            Route::put('/schedules/{inspectionSchedule}', [InspectionScheduleController::class, 'update'])
                ->middleware('role:administrator,barangay_staff');
            Route::patch('/schedules/{inspectionSchedule}/schedule', [InspectionScheduleController::class, 'schedule'])
                ->middleware('role:administrator,barangay_staff');
            Route::delete('/schedules/{inspectionSchedule}', [InspectionScheduleController::class, 'destroy'])
                ->middleware('role:administrator');
            Route::get('/schedules/{inspectionSchedule}/checklist', [ComplianceChecklistController::class, 'show']);
            Route::post('/schedules/{inspectionSchedule}/checklist', [ComplianceChecklistController::class, 'store']);
            Route::get('/schedules/{inspectionSchedule}/report', [InspectionReportController::class, 'show']);
            Route::get('/schedules/{inspectionSchedule}/report/pdf', [InspectionReportController::class, 'pdf']);
            Route::put('/schedules/{inspectionSchedule}/report', [InspectionReportController::class, 'update']);
        });

        Route::get('/inspectors/{inspector}/availability', [InspectionScheduleController::class, 'availability'])
            ->middleware('role:administrator,barangay_staff');

        Route::prefix('violations')->middleware('role:administrator,barangay_staff,inspector')->group(function () {
            Route::get('/options', [ViolationController::class, 'options']);
            Route::get('/', [ViolationController::class, 'index']);
            Route::get('/{violation}', [ViolationController::class, 'show']);
            Route::post('/', [ViolationController::class, 'store']);
            Route::put('/{violation}', [ViolationController::class, 'update']);
            Route::delete('/{violation}', [ViolationController::class, 'destroy'])
                ->middleware('role:administrator,barangay_staff');
            Route::post('/{violation}/evidence', [ViolationController::class, 'storeEvidence']);
            Route::get('/{violation}/pdf', [ViolationController::class, 'pdf']);
        });

        Route::prefix('certifications')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/options', [CertificationController::class, 'options']);
            Route::get('/', [CertificationController::class, 'index']);
            Route::post('/', [CertificationController::class, 'store']);
            Route::get('/{kind}/{id}/pdf', [CertificationController::class, 'downloadPdf']);
            Route::post('/{kind}/{id}/approve', [CertificationController::class, 'approve']);
            Route::post('/{kind}/{id}/revoke', [CertificationController::class, 'revoke']);
            Route::post('/{kind}/{id}/renew', [CertificationController::class, 'renew']);
            Route::put('/{kind}/{id}', [CertificationController::class, 'update']);
            Route::delete('/{kind}/{id}', [CertificationController::class, 'destroy'])
                ->middleware('role:administrator');
        });

        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'globalIndex']);
            Route::patch('/{payment}/confirm', [PaymentController::class, 'confirm'])
                ->middleware('role:administrator,barangay_staff');
            Route::get('/{payment}/receipt-global', [PaymentController::class, 'receiptGlobal']);
        });

        Route::prefix('inspection-requests')->group(function () {
            Route::get('/options', [InspectionRequestController::class, 'options']);
            Route::get('/document-requirements', [InspectionRequestController::class, 'documentRequirements']);
            Route::get('/', [InspectionRequestController::class, 'index']);
            Route::post('/', [InspectionRequestController::class, 'store']);
            Route::get('/queue', [InspectionRequestController::class, 'queue'])
                ->middleware('role:administrator,barangay_staff');
            Route::get('/{inspection_request}', [InspectionRequestController::class, 'show']);
            Route::get('/{inspection_request}/requirements', [InspectionRequestController::class, 'requirements'])
                ->middleware('role:administrator,barangay_staff');
            Route::put('/{inspection_request}/review', [InspectionRequestController::class, 'review'])
                ->middleware('role:administrator,barangay_staff');
            Route::post('/{inspection_request}/assign', [InspectionRequestController::class, 'assign'])
                ->middleware('role:administrator,barangay_staff');
            Route::put('/{inspection_request}/preferred-schedule', [InspectionRequestController::class, 'setPreferredSchedule']);
            Route::delete('/{inspection_request}/preferred-schedule', [InspectionRequestController::class, 'clearPreferredSchedule']);
            Route::post('/{inspection_request}/confirm-schedule', [InspectionScheduleController::class, 'confirmPreferred'])
                ->middleware('role:administrator,barangay_staff');

            Route::get('/{inspection_request}/payments', [PaymentController::class, 'index']);
            Route::post('/{inspection_request}/payments', [PaymentController::class, 'store'])
                ->middleware('role:administrator,barangay_staff');
            Route::get('/{inspection_request}/payments/{payment}/receipt', [PaymentController::class, 'receipt']);

            Route::get('/{inspection_request}/documents', [DocumentController::class, 'index']);
            Route::post('/{inspection_request}/documents', [DocumentController::class, 'upload']);
            Route::get('/{inspection_request}/documents/{document}/download', [DocumentController::class, 'downloadFromRequest']);
        });

        Route::prefix('inspection-assignments')->middleware('role:administrator,barangay_staff,inspector')->group(function () {
            Route::get('/', [InspectionAssignmentController::class, 'index']);
            Route::get('/{inspection_assignment}', [InspectionAssignmentController::class, 'show']);
            Route::put('/{inspection_assignment}/start', [InspectionAssignmentController::class, 'start'])
                ->middleware('role:inspector');
            Route::put('/{inspection_assignment}/submit', [InspectionAssignmentController::class, 'submit'])
                ->middleware('role:inspector');
            Route::get('/{inspection_assignment}/checklist', [InspectionAssignmentController::class, 'checklist'])
                ->middleware('role:inspector');
            Route::post('/{inspection_assignment}/checklist', [InspectionAssignmentController::class, 'saveChecklist'])
                ->middleware('role:inspector');
            Route::get('/{inspection_assignment}/report', [InspectionAssignmentController::class, 'report'])
                ->middleware('role:inspector');
            Route::put('/{inspection_assignment}/report', [InspectionAssignmentController::class, 'updateReport'])
                ->middleware('role:inspector');
        });

        Route::prefix('follow-up')->group(function () {
            Route::get('/', [FollowUpController::class, 'index']);
            Route::post('/', [FollowUpController::class, 'request']);
            Route::get('/{inspection_request}', [FollowUpController::class, 'show']);
        });

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        });

        Route::prefix('checklist-templates')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/', [ChecklistTemplateController::class, 'index']);
            Route::post('/', [ChecklistTemplateController::class, 'store']);
            Route::get('/{checklist}', [ChecklistTemplateController::class, 'show']);
            Route::put('/{checklist}', [ChecklistTemplateController::class, 'update']);
            Route::delete('/{checklist}', [ChecklistTemplateController::class, 'destroy'])
                ->middleware('role:administrator');
        });

        Route::prefix('reports')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/inspections', [ReportController::class, 'inspections']);
            Route::get('/violations', [ReportController::class, 'violations']);
            Route::get('/clearances', [ReportController::class, 'clearances']);
            Route::get('/dashboard', [ReportController::class, 'dashboard']);
            Route::get('/soba', [ReportController::class, 'soba']);
        });

        Route::prefix('audit-logs')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/filters', [AuditLogController::class, 'filters'])
                ->middleware('role:administrator');
            Route::get('/export', [AuditLogController::class, 'export'])
                ->middleware('role:administrator');
            Route::post('/archive', [AuditLogController::class, 'archive'])
                ->middleware('role:administrator');
        });

        Route::prefix('documents')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/{document}', [DocumentController::class, 'show']);
            Route::get('/{document}/ocr', [DocumentController::class, 'ocrResult']);
            Route::get('/{document}/download', [DocumentController::class, 'download']);
            Route::post('/{document}/process-ocr', [DocumentController::class, 'processOcr']);
            Route::post('/{document}/reprocess', [DocumentController::class, 'reprocessOcr']);
            Route::put('/{document}/extraction', [DocumentController::class, 'updateExtraction']);
            Route::put('/{document}/verify', [DocumentController::class, 'verify']);
        });

        Route::prefix('admin')->middleware('role:administrator')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        });

        Route::prefix('admin/ocr-results')->middleware('role:administrator,barangay_staff')->group(function () {
            Route::get('/', [OcrResultController::class, 'index']);
            Route::get('/stats', [OcrResultController::class, 'stats']);
            Route::get('/{document}/history', [OcrResultController::class, 'history']);
            Route::get('/{document}', [OcrResultController::class, 'show']);
            Route::patch('/{document}/fields', [OcrResultController::class, 'updateFields']);
            Route::post('/{document}/verify', [OcrResultController::class, 'verify']);
            Route::post('/{document}/reject', [OcrResultController::class, 'reject']);
            Route::post('/{document}/request-reupload', [OcrResultController::class, 'requestReupload']);
            Route::post('/{document}/reprocess', [OcrResultController::class, 'reprocess']);
        });
    });
});
