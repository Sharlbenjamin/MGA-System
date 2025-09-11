<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's backup any existing data
        $existingData = DB::table('branch_services')->get();
        
        // Drop the existing table
        Schema::dropIfExists('branch_services');
        
        // Create the new pivot table structure
        Schema::create('branch_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_branch_id')->constrained('provider_branches')->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained('service_types')->onDelete('cascade');
            $table->decimal('day_cost', 8, 2)->nullable();
            $table->decimal('weekend_night_cost', 8, 2)->nullable();
            $table->timestamps();
            
            // Ensure unique combination of branch and service type
            $table->unique(['provider_branch_id', 'service_type_id']);
        });
        
        // Migrate existing data if any
        if ($existingData->isNotEmpty()) {
            foreach ($existingData as $record) {
                DB::table('branch_service')->insert([
                    'provider_branch_id' => $record->provider_branch_id,
                    'service_type_id' => $record->service_type_id,
                    'day_cost' => $record->day_cost,
                    'weekend_night_cost' => $record->weekend_night_cost,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backup the pivot table data
        $pivotData = DB::table('branch_service')->get();
        
        // Drop the pivot table
        Schema::dropIfExists('branch_service');
        
        // Recreate the original branch_services table
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
        
        // Restore data if any
        if ($pivotData->isNotEmpty()) {
            foreach ($pivotData as $record) {
                DB::table('branch_services')->insert([
                    'provider_branch_id' => $record->provider_branch_id,
                    'service_type_id' => $record->service_type_id,
                    'day_cost' => $record->day_cost,
                    'night_cost' => null,
                    'weekend_cost' => null,
                    'weekend_night_cost' => $record->weekend_night_cost,
                    'is_active' => true,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                ]);
            }
        }
    }
};
