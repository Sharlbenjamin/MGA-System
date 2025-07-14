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
        Schema::table('leads', function (Blueprint $table) {
            // Drop the enum column and recreate it as a string
            $table->dropColumn('status');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('status')->after('first_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->enum('status', ['Introduction', 'Introduction Sent','Reminder', 'Reminder Sent','Presentation', 'Presentation Sent','Price List', 'Price List Sent','Contract', 'Contract Sent','Interested', 'Error', 'Partner', 'Rejected'])->after('first_name');
        });
    }
};
