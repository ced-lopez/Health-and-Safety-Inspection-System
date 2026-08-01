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
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('business_permit', 'barangay_id', 'safety_certificate', 'business_registration', 'other'))");

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'processed', 'verified', 'rejected'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('business_permit', 'safety_certificate', 'other'))");

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'verified', 'rejected'))");
    }
};
