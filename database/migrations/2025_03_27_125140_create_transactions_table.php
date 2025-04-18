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
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->string('related_type');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('type');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->decimal('bank_charges', 15, 2)->default(0);
            $table->boolean('charges_covered_by_client')->default(false);
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
