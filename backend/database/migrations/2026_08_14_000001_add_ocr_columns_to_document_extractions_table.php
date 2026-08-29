<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->string('ocr_status')->nullable()->index()->after('document_id');
            $table->decimal('classification_confidence', 5, 4)->nullable()->after('confidence_score');
            $table->longText('ocr_text')->nullable()->after('classification_confidence');
            $table->unsignedBigInteger('processing_time_ms')->nullable()->after('ocr_text');
            $table->text('ocr_error')->nullable()->after('processing_time_ms');
            $table->json('low_confidence_fields')->nullable()->after('missing_requirements');
        });
    }

    public function down(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->dropColumn([
                'ocr_status',
                'ocr_text',
                'processing_time_ms',
                'ocr_error',
                'low_confidence_fields',
                'classification_confidence',
            ]);
        });
    }
};
