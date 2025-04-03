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

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('mga_reference')->unique();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('service_type_id')->constrained();
            $table->string('status')->default("New");
            $table->string('client_reference')->nullable();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->foreignId('city_id')->nullable()->constrained();
            $table->foreignId('provider_branch_id')->nullable()->constrained();
            $table->date('service_date')->nullable();
            $table->time('service_time')->nullable();
            $table->string('address')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('contact_patient')->nullable()->default('Client');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
