<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });

        Schema::table('file_assignments', function (Blueprint $table) {
            $table->index(['file_id', 'is_primary']);
            $table->index(['user_id', 'assigned_at']);
            $table->index(['file_id', 'user_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_assignments');
    }
};
