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
        Schema::table('providers', function (Blueprint $table) {
            $table->text('request_comment')->nullable()->after('comment');
        });

        Schema::table('provider_branches', function (Blueprint $table) {
            $table->text('request_comment')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('request_comment');
        });

        Schema::table('provider_branches', function (Blueprint $table) {
            $table->dropColumn('request_comment');
        });
    }
};
