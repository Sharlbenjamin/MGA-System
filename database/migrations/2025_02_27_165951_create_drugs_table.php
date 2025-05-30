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

        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->string('name');
            $table->string('pharmaceutical');
            $table->string('dose');
            $table->string('duration');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
