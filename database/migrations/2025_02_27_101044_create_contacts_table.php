<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id');
            $table->enum('type', ["Client", "Provider", "Branch", "Patient"]);
            $table->uuid('client_id')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('second_email')->unique()->nullable();
            $table->string('phone_number')->nullable();
            $table->string('second_phone')->nullable();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->foreignId('city_id')->nullable()->constrained();
            $table->string('address')->nullable();
            $table->string('preferred_contact')->nullable();
            $table->enum('status', ["Active", "Inactive"])->nullable();
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
