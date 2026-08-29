<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->dateTime('preferred_schedule_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->dropColumn('preferred_schedule_at');
        });
    }
};