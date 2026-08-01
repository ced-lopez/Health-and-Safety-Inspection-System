<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->change();
            $table->foreignId('inspection_request_id')
                ->nullable()
                ->after('inspection_schedule_id')
                ->constrained()
                ->nullOnDelete();
            $table->index('inspection_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropForeign(['inspection_request_id']);
            $table->dropIndex(['inspection_request_id']);
            $table->dropColumn('inspection_request_id');
            $table->foreignId('establishment_id')->nullable(false)->change();
        });
    }
};
