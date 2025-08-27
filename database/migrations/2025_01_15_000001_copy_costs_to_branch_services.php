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
        // Step 1: Copy existing costs from provider_branches to branch_services
        // We'll use the first available service type as default
        $defaultServiceType = DB::table('service_types')->first();
        
        if ($defaultServiceType) {
            DB::statement("
                INSERT INTO branch_services (
                    provider_branch_id, 
                    service_type_id, 
                    day_cost, 
                    night_cost, 
                    weekend_cost, 
                    weekend_night_cost, 
                    is_active, 
                    created_at, 
                    updated_at
                )
                SELECT 
                    id as provider_branch_id,
                    {$defaultServiceType->id} as service_type_id,
                    COALESCE(day_cost, 0) as day_cost,
                    COALESCE(night_cost, 0) as night_cost,
                    COALESCE(weekend_cost, 0) as weekend_cost,
                    COALESCE(weekend_night_cost, 0) as weekend_night_cost,
                    1 as is_active,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM provider_branches 
                WHERE day_cost IS NOT NULL 
                   OR night_cost IS NOT NULL 
                   OR weekend_cost IS NOT NULL 
                   OR weekend_night_cost IS NOT NULL
                ON DUPLICATE KEY UPDATE
                    day_cost = VALUES(day_cost),
                    night_cost = VALUES(night_cost),
                    weekend_cost = VALUES(weekend_cost),
                    weekend_night_cost = VALUES(weekend_night_cost)
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('service_types')->exists()) {
            $defaultServiceType = DB::table('service_types')->first();
            if ($defaultServiceType) {
                DB::statement("
                    DELETE FROM branch_services 
                    WHERE service_type_id = {$defaultServiceType->id}
                ");
            }
        }
    }
};
