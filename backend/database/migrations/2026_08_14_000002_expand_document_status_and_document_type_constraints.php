<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the documents.status values to include the OCR lifecycle statuses.
 * - PostgreSQL: the constraint was defined as a CHECK constraint (see earlier
 *   migrations), so we drop/re-add it here.
 * - Other drivers (MySQL/SQLite): widen the column to a plain string so the
 *   new values are accepted.
 *
 * Also allows application_form in document_type on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'processing', 'processed', 'needs_review', 'verified', 'rejected', 'failed'))");

            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN (
                'government_id',
                'cedula',
                'proof_of_location',
                'vicinity_map',
                'dti_registration',
                'sec_registration',
                'health_certificate',
                'establishment_photo',
                'application_form',
                'pet_registration_form',
                'rabies_certificate',
                'bai_registration',
                'hoa_clearance',
                'neighbor_waiver',
                'zoning_assessment',
                'waste_management_plan',
                'previous_clearance',
                'business_permit',
                'barangay_id',
                'safety_certificate',
                'business_registration',
                'other'
            ))");

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
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'processed', 'verified', 'rejected'))");

            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN (
                'government_id',
                'cedula',
                'proof_of_location',
                'vicinity_map',
                'dti_registration',
                'sec_registration',
                'health_certificate',
                'establishment_photo',
                'pet_registration_form',
                'rabies_certificate',
                'bai_registration',
                'hoa_clearance',
                'neighbor_waiver',
                'zoning_assessment',
                'waste_management_plan',
                'previous_clearance',
                'business_permit',
                'barangay_id',
                'safety_certificate',
                'business_registration',
                'other'
            ))");

            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processed', 'verified', 'rejected'])->default('pending')->change();
        });
    }
};
