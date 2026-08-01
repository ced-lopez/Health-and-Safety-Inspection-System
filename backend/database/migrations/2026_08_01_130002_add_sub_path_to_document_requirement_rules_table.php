<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirement_rules', function (Blueprint $table) {
            $table->dropUnique('document_requirement_unique');

            $table->string('sub_path')->nullable()->after('application_type_id');

            $table->unique(
                ['inspection_category_id', 'application_type_id', 'sub_path', 'document_type'],
                'document_requirement_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('document_requirement_rules', function (Blueprint $table) {
            $table->dropUnique('document_requirement_unique');

            $table->dropColumn('sub_path');

            $table->unique(
                ['inspection_category_id', 'application_type_id', 'document_type'],
                'document_requirement_unique'
            );
        });
    }
};
