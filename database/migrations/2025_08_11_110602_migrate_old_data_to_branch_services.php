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
        // Get all service types
        $serviceTypes = DB::table('service_types')->get()->keyBy('name');
        
        // Get backup data
        $backupData = DB::table('provider_branches_backup')->get();
        
        foreach ($backupData as $backup) {
            $providerBranch = DB::table('provider_branches')->where('id', $backup->provider_branch_id)->first();
            
            if (!$providerBranch) continue;
            
            // Parse service_types (could be JSON array or comma-separated string)
            $serviceTypesList = [];
            if ($backup->service_types) {
                if (is_string($backup->service_types)) {
                    // Try to decode as JSON first
                    $decoded = json_decode($backup->service_types, true);
                    if (is_array($decoded)) {
                        $serviceTypesList = $decoded;
                    } else {
                        // Treat as comma-separated string
                        $serviceTypesList = array_map('trim', explode(',', $backup->service_types));
                    }
                }
            }
            
            // If no service types found, create a default one
            if (empty($serviceTypesList)) {
                $serviceTypesList = ['General Practice']; // Default service type
            }
            
            // Create branch services for each service type
            foreach ($serviceTypesList as $serviceTypeName) {
                $serviceType = $serviceTypes->get($serviceTypeName);
                
                if (!$serviceType) {
                    // Create service type if it doesn't exist
                    $serviceTypeId = DB::table('service_types')->insertGetId([
                        'name' => $serviceTypeName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $serviceTypeId = $serviceType->id;
                }
                
                // Check if branch service already exists
                $existingBranchService = DB::table('branch_services')
                    ->where('provider_branch_id', $backup->provider_branch_id)
                    ->where('service_type_id', $serviceTypeId)
                    ->first();
                
                if (!$existingBranchService) {
                    DB::table('branch_services')->insert([
                        'provider_branch_id' => $backup->provider_branch_id,
                        'service_type_id' => $serviceTypeId,
                        'day_cost' => $backup->day_cost,
                        'night_cost' => $backup->night_cost,
                        'weekend_cost' => $backup->weekend_cost,
                        'weekend_night_cost' => $backup->weekend_night_cost,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't need to be reversed as it's a data migration
        // The backup table will still exist for manual recovery if needed
    }
};
