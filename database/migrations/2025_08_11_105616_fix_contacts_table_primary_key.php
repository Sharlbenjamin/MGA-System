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
        // Check if primary key already exists
        $hasPrimaryKey = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.table_constraints 
            WHERE table_schema = DATABASE() 
            AND table_name = 'contacts' 
            AND constraint_type = 'PRIMARY KEY'
        ")[0]->count > 0;

        if (!$hasPrimaryKey) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->primary('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if primary key exists before trying to drop it
        $hasPrimaryKey = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.table_constraints 
            WHERE table_schema = DATABASE() 
            AND table_name = 'contacts' 
            AND constraint_type = 'PRIMARY KEY'
        ")[0]->count > 0;

        if ($hasPrimaryKey) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropPrimary();
            });
        }
    }
};
