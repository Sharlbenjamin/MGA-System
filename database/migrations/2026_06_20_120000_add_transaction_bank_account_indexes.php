<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['bank_account_id', 'documentation_status'], 'transactions_bank_doc_status_index');
            $table->index(['bank_account_id', 'documentation_category'], 'transactions_bank_doc_category_index');
            $table->index(['bank_account_id', 'date'], 'transactions_bank_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_bank_doc_status_index');
            $table->dropIndex('transactions_bank_doc_category_index');
            $table->dropIndex('transactions_bank_date_index');
        });
    }
};
