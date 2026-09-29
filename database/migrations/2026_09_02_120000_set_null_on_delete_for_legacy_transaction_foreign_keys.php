<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'transaction_id')) {
            return;
        }

        $foreignKey = $this->findTransactionIdForeignKey($tableName);

        if ($foreignKey !== null) {
            if (strtoupper((string) $foreignKey->delete_rule) === 'SET NULL') {
                return;
            }

            Schema::table($tableName, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey->constraint_name);
            });
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });
    }

    /**
     * @return object{constraint_name: string, delete_rule: string}|null
     */
    private function findTransactionIdForeignKey(string $tableName): ?object
    {
        $database = Schema::getConnection()->getDatabaseName();

        $rows = DB::select(
            <<<'SQL'
SELECT
    kcu.CONSTRAINT_NAME AS constraint_name,
    rc.DELETE_RULE AS delete_rule
FROM information_schema.KEY_COLUMN_USAGE kcu
INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
    AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
    AND rc.TABLE_NAME = kcu.TABLE_NAME
WHERE kcu.TABLE_SCHEMA = ?
    AND kcu.TABLE_NAME = ?
    AND kcu.COLUMN_NAME = 'transaction_id'
    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1
SQL,
            [$database, $tableName]
        );

        return $rows[0] ?? null;
    }
};
