<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->index();
            $table->string('context_type')->index();
            $table->string('channel')->index();
            $table->string('subject_template')->nullable();
            $table->longText('body_template');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['key', 'channel']);
        });

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->index();
            $table->nullableMorphs('recipient');
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_branch_id')->nullable()->constrained('provider_branches')->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('context_type')->index();
            $table->unsignedBigInteger('context_id')->nullable()->index();
            $table->foreignId('template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('status')->index();
            $table->string('external_message_id')->nullable()->index();
            $table->string('external_thread_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('communication_templates');
    }
};
