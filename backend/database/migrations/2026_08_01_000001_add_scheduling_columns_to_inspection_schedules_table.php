<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_schedules', function (Blueprint $table) {
            $table->foreignId('inspection_request_id')
                ->nullable()
                ->after('scheduled_by')
                ->constrained('inspection_requests')
                ->nullOnDelete();

            $table->foreignId('inspection_assignment_id')
                ->nullable()
                ->after('inspection_request_id')
                ->constrained('inspection_assignments')
                ->nullOnDelete();

            $table->timestamp('scheduled_at')->nullable()->after('scheduled_time');
            $table->string('schedule_type')->default('initial')->after('scheduled_at');
            $table->unsignedInteger('server_version')->default(1)->after('schedule_type');

            $table->index(['inspector_id', 'scheduled_at']);
        });

        foreach (DB::table('inspection_schedules')->whereNull('scheduled_at')->get() as $row) {
            $time = $row->scheduled_time !== null ? substr((string) $row->scheduled_time, 0, 5) : '00:00';
            DB::table('inspection_schedules')
                ->where('id', $row->id)
                ->update(['scheduled_at' => "{$row->scheduled_date} {$time}:00"]);
        }
    }

    public function down(): void
    {
        Schema::table('inspection_schedules', function (Blueprint $table) {
            $table->dropForeign(['inspection_request_id']);
            $table->dropForeign(['inspection_assignment_id']);
            $table->dropIndex(['inspector_id', 'scheduled_at']);
            $table->dropColumn(['inspection_request_id', 'inspection_assignment_id', 'scheduled_at', 'schedule_type', 'server_version']);
        });
    }
};
