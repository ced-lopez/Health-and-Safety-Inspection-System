<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('classification')->nullable();
            $table->json('extracted_data')->nullable();
            $table->string('business_name')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('permit_number')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->date('date_issued')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('certificate_name')->nullable();
            $table->string('certificate_number')->nullable();
            $table->string('establishment_name')->nullable();
            $table->string('issuing_office')->nullable();
            $table->date('issue_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->json('missing_requirements')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};
