<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds needs_reupload to the documents.status lifecycle used by the OCR
 * Results review workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'processing', 'processed', 'needs_review', 'needs_reupload', 'verified', 'rejected', 'failed'))");

            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'processing', 'processed', 'needs_review', 'verified', 'rejected', 'failed'))");

            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
