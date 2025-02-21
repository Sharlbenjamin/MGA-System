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
        Schema::disableForeignKeyConstraints();

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->string('email')->unique();
            $table->string('first_name');
            $table->enum('status', ['Introduction', 'Introduction Sent','Reminder', 'Reminder Sent','Presentation', 'Presentation Sent','Price List', 'Price List Sent','Contract', 'Contract Sent','Interested', 'Error', 'Partner', 'Rejected']);
            $table->date('last_contact_date')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
