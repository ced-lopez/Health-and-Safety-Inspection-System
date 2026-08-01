<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN (
            'application_form',
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
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('business_permit', 'barangay_id', 'safety_certificate', 'business_registration', 'other'))");
    }
};
