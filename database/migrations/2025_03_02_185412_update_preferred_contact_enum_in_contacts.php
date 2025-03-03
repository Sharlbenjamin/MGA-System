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
        Schema::table('contacts', function (Blueprint $table) {
            $table->enum('preferred_contact', ["Phone", "Second Phone", "Email", "Second Email", "first_whatsapp", "second_whatsapp"])
                  ->nullable()
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->enum('preferred_contact', ["Phone", "Second Phone", "Email", "Second Email"])
                  ->nullable()
                  ->change();
        });
    }
};
