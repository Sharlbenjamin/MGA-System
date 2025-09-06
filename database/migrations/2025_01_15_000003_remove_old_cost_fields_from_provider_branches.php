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
        Schema::table('provider_branches', function (Blueprint $table) {
            // Remove old cost fields AFTER data has been copied to branch_services
            // Check if columns exist before dropping them
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            // Add back the cost fields if rollback needed
            $table->decimal('day_cost', 8, 2)->nullable();
            $table->decimal('night_cost', 8, 2)->nullable();
            $table->decimal('weekend_cost', 8, 2)->nullable();
            $table->decimal('weekend_night_cost', 8, 2)->nullable();
        });
    }
};
