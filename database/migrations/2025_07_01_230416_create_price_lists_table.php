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
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_branch_id')->nullable()->constrained()->onDelete('set null');
            
            // Price fields
            $table->decimal('day_price', 10, 2)->nullable();
            $table->decimal('weekend_price', 10, 2)->nullable();
            $table->decimal('night_weekday_price', 10, 2)->nullable();
            $table->decimal('night_weekend_price', 10, 2)->nullable();
            
            // Additional fields
            $table->decimal('suggested_markup', 5, 2)->nullable(); // e.g., 1.25 for 25% markup
            $table->text('final_price_notes')->nullable();
            
            // Polymorphic relationship fields (for future extensibility)
            $table->string('priceable_type')->nullable();
            $table->unsignedBigInteger('priceable_id')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['country_id', 'city_id', 'service_type_id']);
            $table->index(['provider_branch_id']);
            $table->index(['priceable_type', 'priceable_id']);
            
            // Unique constraint to prevent duplicate pricing for same criteria
            $table->unique(['country_id', 'city_id', 'service_type_id', 'provider_branch_id'], 'unique_price_criteria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
