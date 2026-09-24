<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archives all document requirement rules except the whitelisted
 * resident upload documents: Valid Government ID and Proof of
 * Residency Clearance (proof_of_location). Barangay ID and all
 * other types (cedula, vicinity_map, previous_clearance,
 * dti_registration, etc.) are deleted so they no longer appear
 * in the resident application upload step. proof_of_location is
 * renamed to Proof of Residency Clearance.
 */
return new class extends Migration
{
    private const WHITELIST = ['government_id', 'proof_of_location'];

    public function up(): void
    {
        DB::table('document_requirement_rules')
            ->whereNotIn('document_type', self::WHITELIST)
            ->delete();

        DB::table('document_requirement_rules')
            ->where('document_type', 'proof_of_location')
            ->update(['document_name' => 'Proof of Residency Clearance']);

        $archivedIds = DB::table('documents')
            ->where('documentable_type', 'App\\Models\\InspectionRequest')
            ->whereNotIn('document_type', self::WHITELIST)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($archivedIds->isNotEmpty()) {
            DB::table('documents')
                ->whereIn('id', $archivedIds)
                ->update(['deleted_at' => now()]);
        }

        if (Schema::hasColumn('document_requirement_rules', 'notes')) {
            // placeholder for future archived_at column
        }
    }

    public function down(): void
    {
        DB::table('documents')
            ->whereNotIn('document_type', self::WHITELIST)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);

        DB::table('document_requirement_rules')
            ->where('document_type', 'proof_of_location')
            ->update(['document_name' => 'Proof of Business/Residency Location']);
    }
};
