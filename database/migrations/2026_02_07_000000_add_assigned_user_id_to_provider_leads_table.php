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
        Schema::table('provider_leads', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('provider_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_leads', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
        });
    }
};
