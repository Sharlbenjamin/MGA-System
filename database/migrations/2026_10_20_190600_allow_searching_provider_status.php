<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow the provider status Searching when every lead is Error.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `providers` MODIFY `status` VARCHAR(255) NOT NULL");

            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->string('status')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE `providers` SET `status` = 'Potential' WHERE `status` = 'Searching'");
            DB::statement("ALTER TABLE `providers` MODIFY `status` ENUM('Active','Hold','Potential','Black') NOT NULL");

            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->string('status')->change();
        });
    }
};
