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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('transaction_group_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->string('related_type');
            $table->unsignedBigInteger('related_id');
            $table->decimal('amount', 15, 2);
            $table->string('type');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
