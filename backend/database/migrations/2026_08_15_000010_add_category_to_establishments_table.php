<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the establishment category that drives the applicable compliance
        // checklist for each establishment during inspection. Existing records
        // are backfilled heuristically from their current business_type.
        Schema::table('establishments', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('business_type');
        });

        $maps = [
            'piggery' => ['piggery'],
            'poultry' => ['poultry', 'chicken', 'broiler'],
            'dog_raising_kennel' => ['dog', 'kennel', 'animal raising', 'animal_raising'],
        ];

        $establishments = DB::table('establishments')
            ->select(['id', 'business_type'])
            ->whereNull('category')
            ->get();

        foreach ($establishments as $establishment) {
            $businessType = (string) ($establishment->business_type ?? '');
            $businessTypeLower = strtolower($businessType);
            $category = 'food_establishment';

            foreach ($maps as $candidate => $needles) {
                foreach ($needles as $needle) {
                    if (str_contains($businessTypeLower, $needle)) {
                        $category = $candidate;
                        break 2;
                    }
                }
            }

            DB::table('establishments')
                ->where('id', $establishment->id)
                ->update(['category' => $category]);
        }

        Schema::table('establishments', function (Blueprint $table) {
            $table->string('category', 50)->nullable(false)->default('food_establishment')->change();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};