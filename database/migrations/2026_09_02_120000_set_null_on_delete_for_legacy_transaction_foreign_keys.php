<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore ON DELETE SET NULL on the leftover bills/invoices.transaction_id
     * columns. Production currently rejects transaction deletes with 1451 because
     * those FKs were created as RESTRICT.
     */
    public function up(): void
    {
        $this->recreateLegacyTransactionForeignKey('bills');
        $this->recreateLegacyTransactionForeignKey('invoices');
    }

    public function down(): void
    {
        // Keep SET NULL; that matches the original bills/invoices create migrations.
    }

    private function recreateLegacyTransactionForeignKey(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });
    }
};
