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
        Schema::table('contacts', function (Blueprint $table) {
            // Drop the existing UUID columns
            $table->dropColumn(['client_id', 'provider_id', 'branch_id', 'patient_id']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Add the correct integer foreign key columns
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            
            // Add foreign key constraints
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('provider_branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['client_id']);
            $table->dropForeign(['provider_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['patient_id']);
            
            // Drop the integer columns
            $table->dropColumn(['client_id', 'provider_id', 'branch_id', 'patient_id']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Add back the UUID columns
            $table->uuid('client_id')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('patient_id')->nullable();
        });
    }
};
