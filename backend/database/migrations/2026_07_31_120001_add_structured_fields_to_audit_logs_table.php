<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module')->nullable()->after('event');
            $table->string('action')->nullable()->after('module');
            $table->text('description')->nullable()->after('action');
            $table->string('table_name')->nullable()->after('description');
            $table->unsignedBigInteger('record_id')->nullable()->after('table_name');
        });

        DB::table('audit_logs')->orderBy('id')->chunkById(500, function ($logs) {
            foreach ($logs as $log) {
                [$module, $action] = $this->resolveEvent($log->event);
                $tableName = null;

                if ($log->auditable_type && class_exists($log->auditable_type)) {
                    try {
                        $tableName = (new $log->auditable_type)->getTable();
                    } catch (Throwable) {
                        $tableName = null;
                    }
                }

                DB::table('audit_logs')->where('id', $log->id)->update([
                    'module' => $module,
                    'action' => $action,
                    'table_name' => $tableName,
                    'record_id' => $log->auditable_id,
                ]);
            }
        });
    }

    private function resolveEvent(string $event): array
    {
        $map = [
            'auth.registered' => ['Authentication', 'Register'],
            'auth.verified' => ['Authentication', 'Email Verified'],
            'auth.verification_resent' => ['Authentication', 'Verification Resent'],
            'auth.verification_required' => ['Authentication', 'Verification Required'],
            'auth.login' => ['Authentication', 'Login'],
            'auth.logout' => ['Authentication', 'Logout'],
            'admin.user.created' => ['Users', 'Created'],
            'admin.user.updated' => ['Users', 'Updated'],
            'admin.user.deleted' => ['Users', 'Deleted'],
            'inspection_request.reviewed' => ['Inspection Request', 'Reviewed'],
            'inspection_request.assigned' => ['Inspector Assignment', 'Assigned'],
            'document.viewed' => ['Documents', 'Viewed'],
            'document.ocr_processed' => ['Documents', 'Processed'],
            'document.verified' => ['Documents', 'Verified'],
            'certification.approved' => ['Certification', 'Approved'],
            'certification.revoked' => ['Certification', 'Revoked'],
            'certification.renewed' => ['Certification', 'Renewed'],
            'clearance.approved' => ['Clearance', 'Approved'],
            'clearance.revoked' => ['Clearance', 'Revoked'],
            'clearance.renewed' => ['Clearance', 'Renewed'],
            'establishment_claim.approved' => ['Establishment Claim', 'Approved'],
            'establishment_claim.rejected' => ['Establishment Claim', 'Rejected'],
        ];

        return $map[$event] ?? ['General', ucfirst($event)];
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['module', 'action', 'description', 'table_name', 'record_id']);
        });
    }
};
