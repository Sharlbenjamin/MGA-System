<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('draft_mails', function (Blueprint $table) {
            $table->id();
            $table->string('mail_name'); // Descriptive name of the mail
            $table->text('body_mail'); // Email content
            $table->string('status'); // The lead status this draft applies to
            $table->string('type')->nullable(); // Email type if needed
            $table->string('new_status'); // Status to update lead after sending
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_mails');
    }
};
