<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->enum('status', ['open', 'under_review', 'overdue', 'resolved'])->default('open')->change();
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->enum('status', ['open', 'under_review', 'resolved'])->default('open')->change();
        });
    }
};
