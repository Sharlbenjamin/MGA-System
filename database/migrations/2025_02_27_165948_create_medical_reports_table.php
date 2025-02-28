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

        Schema::create('medical_reports', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('status', ["Waiting","Received","Not Sent","Sent"]);
            $table->foreignId('file_id')->constrained();
            $table->longText('complain')->nullable();
            $table->longText('diagnosis')->nullable();
            $table->longText('history')->nullable();
            $table->string('temperature')->nullable();
            $table->string('blood_pressure')->nullable();
            $table->string('pulse')->nullable();
            $table->longText('examination')->nullable();
            $table->longText('advice')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_reports');
    }
};
