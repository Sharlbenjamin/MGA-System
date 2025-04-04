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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->enum('type', ["Assistance","Insurance","Agency"]);
            $table->enum('status', ["Searching","Interested","Sent","Rejected","Active","On Hold", "Closed", "Broker", "No Reply"]);
            $table->string('initials', 10);
            $table->integer('number_requests');
            $table->foreignId('gop_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->foreignId('operation_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->foreignId('financial_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
