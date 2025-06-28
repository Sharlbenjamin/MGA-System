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
        // Fix any existing lowercase gender values to uppercase
        DB::table('patients')
            ->where('gender', 'male')
            ->update(['gender' => 'Male']);
            
        DB::table('patients')
            ->where('gender', 'female')
            ->update(['gender' => 'Female']);
            
        DB::table('patients')
            ->where('gender', 'other')
            ->update(['gender' => 'Other']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to lowercase if needed
        DB::table('patients')
            ->where('gender', 'Male')
            ->update(['gender' => 'male']);
            
        DB::table('patients')
            ->where('gender', 'Female')
            ->update(['gender' => 'female']);
            
        DB::table('patients')
            ->where('gender', 'Other')
            ->update(['gender' => 'other']);
    }
};
