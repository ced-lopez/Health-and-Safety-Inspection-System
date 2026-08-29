<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->string('ocr_engine')->nullable()->after('ocr_status');
            $table->string('ocr_language')->nullable()->after('ocr_engine');
            $table->longText('ocr_raw_text')->nullable()->after('ocr_text');
            $table->unsignedSmallInteger('ocr_passes')->nullable()->after('ocr_raw_text');
            $table->json('ocr_versions')->nullable()->after('ocr_passes');
        });
    }

    public function down(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->dropColumn([
                'ocr_engine',
                'ocr_language',
                'ocr_raw_text',
                'ocr_passes',
                'ocr_versions',
            ]);
        });
    }
};
