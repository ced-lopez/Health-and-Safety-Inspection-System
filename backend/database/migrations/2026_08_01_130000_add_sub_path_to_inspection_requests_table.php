<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->string('sub_path')->nullable()->after('application_type_id');
            $table->unsignedTinyInteger('declared_animal_count')->nullable()->after('sub_path');

            $table->index(['inspection_category_id', 'sub_path']);
        });
    }

    public function down(): void
    {
        Schema::table('inspection_requests', function (Blueprint $table) {
            $table->dropIndex(['inspection_category_id', 'sub_path']);
            $table->dropColumn(['declared_animal_count', 'sub_path']);
        });
    }
};
