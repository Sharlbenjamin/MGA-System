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
        Schema::table('provider_branches', function (Blueprint $table) {
            // Only add missing contact fields - DO NOT remove old fields yet
            // The old fields will be removed in a separate migration after data migration
            
            // Check if fields already exist before adding them
            if (!Schema::hasColumn('provider_branches', 'operation_contact_id')) {
                $table->uuid('operation_contact_id')->nullable();
                $table->foreign('operation_contact_id')->references('id')->on('contacts')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('provider_branches', 'financial_contact_id')) {
                $table->uuid('financial_contact_id')->nullable();
                $table->foreign('financial_contact_id')->references('id')->on('contacts')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_branches', function (Blueprint $table) {
            // Remove foreign key constraints
            $table->dropForeign(['operation_contact_id']);
            $table->dropForeign(['financial_contact_id']);
            
            // Remove contact fields
            $table->dropColumn([
                'operation_contact_id',
                'financial_contact_id'
            ]);
        });
    }
};
