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
        // Verify that data migration was successful
        $backupCount = DB::table('provider_branches_backup')->count();
        $branchServicesCount = DB::table('branch_services')->count();
        
        // Only proceed if we have successfully migrated the data
        if ($backupCount > 0 && $branchServicesCount > 0) {
            Schema::table('provider_branches', function (Blueprint $table) {
                // Remove old fields only after data migration is confirmed
                if (Schema::hasColumn('provider_branches', 'service_types')) {
                    $table->dropColumn('service_types');
                }
                
                if (Schema::hasColumn('provider_branches', 'day_cost')) {
                    $table->dropColumn('day_cost');
                }
                
                if (Schema::hasColumn('provider_branches', 'night_cost')) {
                    $table->dropColumn('night_cost');
                }
                
                if (Schema::hasColumn('provider_branches', 'weekend_cost')) {
                    $table->dropColumn('weekend_cost');
                }
                
                if (Schema::hasColumn('provider_branches', 'weekend_night_cost')) {
                    $table->dropColumn('weekend_night_cost');
                }
            });
        } else {
            // Log warning if data migration wasn't successful
            \Log::warning('Provider branches data migration not completed. Old fields will not be removed.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old fields (for rollback purposes)
        Schema::table('provider_branches', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_branches', 'service_types')) {
                $table->string('service_types')->nullable();
            }
            
            if (!Schema::hasColumn('provider_branches', 'day_cost')) {
                $table->decimal('day_cost', 8, 2)->nullable();
            }
            
            if (!Schema::hasColumn('provider_branches', 'night_cost')) {
                $table->decimal('night_cost', 8, 2)->nullable();
            }
            
            if (!Schema::hasColumn('provider_branches', 'weekend_cost')) {
                $table->decimal('weekend_cost', 8, 2)->nullable();
            }
            
            if (!Schema::hasColumn('provider_branches', 'weekend_night_cost')) {
                $table->decimal('weekend_night_cost', 8, 2)->nullable();
            }
        });
    }
};
