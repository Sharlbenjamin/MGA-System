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
        Schema::create('communication_threads', function (Blueprint $table) {
            $table->id();
            $table->string('mailbox')->default('mga.operation@medguarda.com');
            $table->string('subject')->nullable();
            $table->string('normalized_subject')->nullable()->index();
            $table->enum('category', ['client', 'provider', 'general', 'unlinked'])->default('unlinked')->index();
            $table->foreignId('linked_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->boolean('is_read')->default(false)->index();
            $table->boolean('awaiting_reply')->default(false)->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_incoming_at')->nullable()->index();
            $table->string('external_thread_key')->nullable()->index();
            $table->json('participants')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_thread_id')->constrained('communication_threads')->cascadeOnDelete();
            $table->string('mailbox')->default('mga.operation@medguarda.com');
            $table->unsignedBigInteger('mailbox_uid')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('in_reply_to')->nullable()->index();
            $table->enum('direction', ['incoming', 'outgoing'])->index();
            $table->string('from_email')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to_emails')->nullable();
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->boolean('is_unread')->default(false)->index();
            $table->boolean('has_attachments')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['mailbox', 'mailbox_uid']);
        });

        Schema::create('communication_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_message_id')->constrained('communication_messages')->cascadeOnDelete();
            $table->string('filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('part_number')->nullable();
            $table->string('content_id')->nullable();
            $table->string('disposition')->nullable();
            $table->string('external_id')->nullable();
            $table->string('url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('mailbox')->unique();
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->timestamp('last_polled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_attachments');
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_threads');
        Schema::dropIfExists('communication_sync_states');
    }
};
