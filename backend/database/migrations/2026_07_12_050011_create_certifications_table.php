<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('certificate_type');
            $table->date('issue_date');
            $table->date('expiration_date')->nullable();
            $table->enum('status', ['pending', 'active', 'expired', 'revoked'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
