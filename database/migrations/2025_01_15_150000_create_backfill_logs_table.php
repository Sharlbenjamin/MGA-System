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
        Schema::create('backfill_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model_class');
            $table->unsignedBigInteger('model_id');
            $table->string('field');
            $table->string('category');
            $table->text('google_link');
            $table->text('error_message');
            $table->integer('attempts')->default(1);
            $table->string('status')->default('failed'); // failed, retrying, success
            $table->timestamp('last_attempt_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['model_class', 'model_id']);
            $table->index(['status', 'last_attempt_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backfill_logs');
    }
};
