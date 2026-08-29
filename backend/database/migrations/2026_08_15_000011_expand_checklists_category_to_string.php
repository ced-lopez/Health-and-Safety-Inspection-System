<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The checklist category must be able to hold the four establishment
        // categories (food_establishment, piggery, poultry, dog_raising_kennel)
        // that drive which compliance checklist applies during inspection.
        Schema::table('checklists', function (Blueprint $table) {
            $table->string('category', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->enum('category', [
                'health_sanitation',
                'fire_safety',
                'workplace_safety',
            ])->nullable(false)->change();
        });
    }
};