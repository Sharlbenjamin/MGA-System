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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_account_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('status');
            $table->date('payment_date')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->string('bill_google_link')->nullable();
            $table->date('bill_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
