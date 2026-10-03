<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('cv_path')->nullable()->after('photo_id_path');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->decimal('expected_salary', 12, 2)->nullable()->after('social_insurance_salary');
            $table->decimal('offered_salary', 12, 2)->nullable()->after('expected_salary');
            $table->dateTime('interview_date')->nullable()->after('offered_salary');
            $table->string('linkedin_url')->nullable()->after('interview_date');
            $table->string('employment_type')->nullable()->comment('full_time, part_time')->after('linkedin_url');
            $table->string('notice_period')->nullable()->after('employment_type');
            $table->string('english_level')->nullable()->after('notice_period');
            $table->boolean('flexible_shifts')->nullable()->after('english_level');
            $table->text('reason_for_leaving')->nullable()->after('flexible_shifts');
            $table->boolean('has_laptop')->nullable()->after('reason_for_leaving');
        });

        DB::table('employees')->where('status', 'inactive')->update(['status' => 'former']);
    }

    public function down(): void
    {
        DB::table('employees')->where('status', 'former')->update(['status' => 'inactive']);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'end_date',
                'cv_path',
                'gender',
                'expected_salary',
                'offered_salary',
                'interview_date',
                'linkedin_url',
                'employment_type',
                'notice_period',
                'english_level',
                'flexible_shifts',
                'reason_for_leaving',
                'has_laptop',
            ]);
        });
    }
};
