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
        Schema::create('transaction_groups', function (Blueprint $table) {
            $table->id();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('bank_charges', 15, 2)->default(0);
            $table->boolean('covered_charges')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_groups');
    }
};
