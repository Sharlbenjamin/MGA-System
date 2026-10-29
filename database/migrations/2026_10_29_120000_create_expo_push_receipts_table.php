<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expo_push_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_id', 64)->unique();
            $table->string('expo_push_token', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_check_at');
            $table->string('state', 20)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['state', 'next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expo_push_receipts');
    }
};
