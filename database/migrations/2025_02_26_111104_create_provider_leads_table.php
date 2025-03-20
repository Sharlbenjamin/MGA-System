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

        Schema::create('provider_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained();
            $table->string('name', 255)->unique();
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('set null');
            $table->string('service_types');
            $table->enum('type', ["Doctor","Clinic","Hospital","Dental"]);
            $table->string('status', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('communication_method', 50);
            $table->date('last_contact_date')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_leads');
    }
};
