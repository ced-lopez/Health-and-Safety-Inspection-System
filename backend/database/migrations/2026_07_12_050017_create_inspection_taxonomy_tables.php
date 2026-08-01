<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_business_details')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'slug']);
        });

        Schema::create('application_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'slug']);
        });

        Schema::create('document_requirement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_type_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_name');
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_expiration_check')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique([
                'inspection_category_id',
                'application_type_id',
                'document_type',
            ], 'document_requirement_unique');
            $table->index(['document_type', 'is_required']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirement_rules');
        Schema::dropIfExists('application_types');
        Schema::dropIfExists('inspection_categories');
    }
};
