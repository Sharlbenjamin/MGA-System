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

        Schema::create('provider_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained();
            $table->string('branch_name', 255);
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('all_country')->default(false);
            $table->enum('status', ["Active","Hold"]);
            $table->integer('priority');
            $table->string('service_types')->nullable();
            $table->string('communication_method', 50)->nullable();
            $table->decimal('day_cost', 8, 2)->nullable();
            $table->decimal('night_cost', 8, 2)->nullable();
            $table->decimal('weekend_cost', 8, 2)->nullable();
            $table->decimal('weekend_night_cost', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_branches');
    }
};
