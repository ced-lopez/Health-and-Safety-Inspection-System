<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->string('verification_status')
                ->default('pending')
                ->index()
                ->after('ocr_status');
            $table->text('reject_reason')->nullable()->after('reviewed_at');
            $table->timestamp('requested_reupload_at')->nullable()->after('reject_reason');
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_edited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn([
                'verification_status',
                'reject_reason',
                'requested_reupload_at',
                'last_edited_at',
            ]);
        });
    }
};
