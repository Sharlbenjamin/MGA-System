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
        Schema::create('branch_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_branch_id')->constrained('provider_branches')->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained('service_types')->onDelete('cascade');
            $table->decimal('day_cost', 8, 2)->nullable();
            $table->decimal('night_cost', 8, 2)->nullable();
            $table->decimal('weekend_cost', 8, 2)->nullable();
            $table->decimal('weekend_night_cost', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Ensure unique combination of branch and service type
            $table->unique(['provider_branch_id', 'service_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_services');
    }
};
