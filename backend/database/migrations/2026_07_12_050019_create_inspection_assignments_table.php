<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', [
                'assigned',
                'downloaded',
                'in_progress',
                'submitted',
                'reviewed',
                'cancelled',
            ])->default('assigned');
            $table->timestamp('assigned_at');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('server_version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['inspector_id', 'status']);
            $table->index(['inspection_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_assignments');
    }
};
