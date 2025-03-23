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

        Schema::create('gops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained();
            $table->enum('type', ["In","Out"]);
            $table->string('status')->default('Not Sent');
            $table->float('amount');
            $table->date('date');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gops');
    }
};
