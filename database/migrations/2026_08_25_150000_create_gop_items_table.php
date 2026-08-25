<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gop_id')->constrained('gops')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('selling_cost', 15, 2)->default(0);
            $table->string('item_type')->default('service');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('gops', function (Blueprint $table) {
            $table->json('offer_sections')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('gops', function (Blueprint $table) {
            $table->dropColumn('offer_sections');
        });

        Schema::dropIfExists('gop_items');
    }
};
