<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('follow_up_of_inspection_id')->nullable()->after('inspection_request_id')
                ->constrained('inspections')->nullOnDelete();
        });

        Schema::table('violations', function (Blueprint $table) {
            $table->foreignId('parent_violation_id')->nullable()->after('inspection_id')
                ->constrained('violations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->dropForeign(['parent_violation_id']);
            $table->dropColumn('parent_violation_id');
        });
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropForeign(['follow_up_of_inspection_id']);
            $table->dropColumn('follow_up_of_inspection_id');
        });
    }
};
