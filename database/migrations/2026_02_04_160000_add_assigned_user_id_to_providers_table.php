<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Assign potential providers to an employee (no task created).
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
        });
    }
};
