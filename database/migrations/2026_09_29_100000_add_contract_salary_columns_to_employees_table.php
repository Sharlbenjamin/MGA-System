<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('full_salary', 12, 2)->nullable()->after('basic_salary');
            $table->decimal('social_insurance_salary', 12, 2)->nullable()->after('full_salary');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['full_salary', 'social_insurance_salary']);
        });
    }
};
