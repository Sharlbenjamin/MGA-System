<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gops', function (Blueprint $table) {
            $table->foreignId('service_type_id')
                ->nullable()
                ->after('provider_branch_id')
                ->constrained('service_types')
                ->nullOnDelete();

            $table->string('service_type_other', 255)
                ->nullable()
                ->after('service_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('gops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_type_id');
            $table->dropColumn('service_type_other');
        });
    }
};
