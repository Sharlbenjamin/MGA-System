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

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('client_id')->constrained();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->foreignId('gop_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->foreignId('operation_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->foreignId('financial_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
