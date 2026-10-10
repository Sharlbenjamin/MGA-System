<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow client statuses such as "Black list" without expanding the MySQL enum.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `clients` MODIFY `status` VARCHAR(255) NOT NULL DEFAULT 'Searching'");

            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('status')->default('Searching')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `clients` MODIFY `status` ENUM('Searching','Interested','Sent','Rejected','Active','On Hold','Closed','Broker','No Reply') NOT NULL");

            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('status')->default('Searching')->change();
        });
    }
};
