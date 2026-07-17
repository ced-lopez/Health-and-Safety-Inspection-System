<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('business_type');
            $table->string('owner_name');
            $table->text('address');
            $table->string('barangay')->default('Barangay 178');
            $table->string('contact_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('registration_number')->nullable()->unique();
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishments');
    }
};
