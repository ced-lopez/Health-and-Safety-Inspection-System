<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->foreignId('resident_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('ownership_status', ['unclaimed', 'pending', 'linked'])
                ->default('unclaimed')
                ->after('resident_id');

            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropForeign(['resident_id']);
            $table->dropIndex(['resident_id']);
            $table->dropColumn(['resident_id', 'ownership_status']);
        });
    }
};
