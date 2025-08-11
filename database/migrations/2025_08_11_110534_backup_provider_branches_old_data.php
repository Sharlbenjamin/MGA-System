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
        // Create backup table for old provider_branches data
        Schema::create('provider_branches_backup', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_branch_id');
            $table->text('service_types')->nullable();
            $table->decimal('day_cost', 8, 2)->nullable();
            $table->decimal('night_cost', 8, 2)->nullable();
            $table->decimal('weekend_cost', 8, 2)->nullable();
            $table->decimal('weekend_night_cost', 8, 2)->nullable();
            $table->timestamps();
            
            $table->foreign('provider_branch_id')->references('id')->on('provider_branches')->onDelete('cascade');
        });

        // Backup existing data
        $providerBranches = DB::table('provider_branches')->get();
        
        foreach ($providerBranches as $branch) {
            DB::table('provider_branches_backup')->insert([
                'provider_branch_id' => $branch->id,
                'service_types' => $branch->service_types ?? null,
                'day_cost' => $branch->day_cost ?? null,
                'night_cost' => $branch->night_cost ?? null,
                'weekend_cost' => $branch->weekend_cost ?? null,
                'weekend_night_cost' => $branch->weekend_night_cost ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_branches_backup');
    }
};
