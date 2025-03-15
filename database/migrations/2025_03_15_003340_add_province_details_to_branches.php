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
                $table->string('province_id')->nullable();
                $table->boolean('emergency')->default(false);
                $table->boolean('pediatrician_emergency')->default(false);
                $table->boolean('dental')->default(false);
                $table->boolean('pediatrician')->default(false);
                $table->boolean('gynecology')->default(false);
                $table->boolean('urology')->default(false);
                $table->boolean('cardiology')->default(false);
                $table->boolean('ophthalmology')->default(false);
                $table->boolean('trauma_orthopedics')->default(false);
                $table->boolean('surgery')->default(false);
                $table->boolean('intensive_care')->default(false);
                $table->boolean('obstetrics_delivery')->default(false);
                $table->boolean('hyperbaric_chamber')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn([
                    'province',
                    'emergency',
                    'pediatrician_emergency',
                    'dental',
                    'pediatrician',
                    'gynecology',
                    'urology',
                    'cardiology',
                    'ophthalmology',
                    'trauma_orthopedics',
                    'surgery',
                    'intensive_care',
                    'obstetrics_delivery',
                    'hyperbaric_chamber'
                ]);
            });
        });
    }
};
