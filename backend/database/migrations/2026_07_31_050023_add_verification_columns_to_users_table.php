<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code_hash');
            $table->string('verification_channel')->nullable()->after('verification_code_expires_at');
            $table->unsignedTinyInteger('verification_attempts')->default(0)->after('verification_channel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'verification_code_hash',
                'verification_code_expires_at',
                'verification_channel',
                'verification_attempts',
            ]);
        });
    }
};
