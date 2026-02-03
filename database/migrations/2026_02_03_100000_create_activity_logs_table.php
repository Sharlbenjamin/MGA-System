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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20); // created, updated, deleted
            $table->string('subject_type'); // Model class e.g. App\Models\File
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_reference')->nullable(); // Display: case ref, provider name, client name
            $table->json('changes')->nullable(); // For updates: {"attribute": {"old": "...", "new": "..."}}
            $table->timestamps();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
