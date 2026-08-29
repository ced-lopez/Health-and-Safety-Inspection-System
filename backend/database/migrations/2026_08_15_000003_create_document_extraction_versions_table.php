<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extraction_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt')->index();
            $table->string('ocr_status')->nullable()->index();
            $table->string('classification')->nullable();
            $table->decimal('classification_confidence', 5, 4)->nullable();
            $table->json('extracted_data')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->longText('ocr_raw_text')->nullable();
            $table->unsignedBigInteger('processing_time_ms')->nullable();
            $table->string('ocr_engine')->nullable();
            $table->string('ocr_language')->nullable();
            $table->unsignedSmallInteger('ocr_passes')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('ocr_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extraction_versions');
    }
};
