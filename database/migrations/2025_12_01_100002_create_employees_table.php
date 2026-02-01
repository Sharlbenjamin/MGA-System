<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->string('signed_contract_path')->nullable();
            $table->boolean('signed_contract')->default(false);
            $table->string('social_insurance_number')->nullable();
            $table->string('photo_id_path')->nullable();
            $table->string('department')->comment('Operation, Financial, Client Network, Provider Network');
            $table->string('status')->default('active')->comment('active, inactive');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
