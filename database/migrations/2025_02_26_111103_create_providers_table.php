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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->enum('status', ["Active","Hold","Potential","Black"]);
            $table->enum('type', ["Doctor","Hospital","Clinic","Dental","Agency"]);
            $table->string('name', 255)->unique();
            $table->integer('payment_due')->nullable();
            $table->enum('payment_method', ["Online Link", "Bank Transfer", "AEAT"])->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
