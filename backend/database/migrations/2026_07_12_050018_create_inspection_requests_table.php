<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('resident_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspection_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('applicant_name');
            $table->unsignedTinyInteger('applicant_age')->nullable();
            $table->text('applicant_address');
            $table->string('contact_number', 20);
            $table->string('email');
            $table->string('business_name')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'requirements_incomplete',
                'approved_for_inspection',
                'assigned',
                'inspection_completed',
                'violation_notice_issued',
                'follow_up_requested',
                'clearance_approved',
                'rejected',
                'cancelled',
            ])->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resident_id', 'status']);
            $table->index(['inspection_category_id', 'application_type_id']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_requests');
    }
};
