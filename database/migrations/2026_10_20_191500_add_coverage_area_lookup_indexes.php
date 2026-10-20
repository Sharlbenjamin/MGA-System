<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('cities', 'cities_country_id_name_index')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->index(['country_id', 'name'], 'cities_country_id_name_index');
            });
        }

        if (! Schema::hasIndex('cities', 'cities_name_index')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->index('name', 'cities_name_index');
            });
        }

        if (! Schema::hasIndex('provider_branches', 'provider_branches_coverage_lookup_index')) {
            Schema::table('provider_branches', function (Blueprint $table) {
                $table->index(['status', 'all_country', 'provider_id'], 'provider_branches_coverage_lookup_index');
            });
        }

        if (! Schema::hasIndex('providers', 'providers_status_country_id_index')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->index(['status', 'country_id'], 'providers_status_country_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('cities_country_id_name_index');
            $table->dropIndex('cities_name_index');
        });

        Schema::table('provider_branches', function (Blueprint $table) {
            $table->dropIndex('provider_branches_coverage_lookup_index');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex('providers_status_country_id_index');
        });
    }
};
