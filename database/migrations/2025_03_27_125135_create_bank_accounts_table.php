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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->uuid('client_id')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('file_id')->nullable();
            $table->string('beneficiary_name');
            $table->string('iban');
            $table->string('swift')->nullable();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->string('bank_name')->nullable();
            $table->text('beneficiary_address')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
