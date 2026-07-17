<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->restrictOnDelete();
            $table->enum('compliance_status', [
                'compliant',
                'non_compliant',
                'needs_correction',
            ]);
            $table->text('remarks')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['inspection_id', 'checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
