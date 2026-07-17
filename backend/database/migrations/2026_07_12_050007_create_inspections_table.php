<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->date('inspection_date');
            $table->enum('status', [
                'scheduled',
                'ongoing',
                'completed',
                'cancelled',
            ])->default('scheduled');
            $table->text('overall_assessment')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'inspection_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
