<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->timestamp('billing_mismatch_accepted_at')->nullable()->after('phone');
            $table->foreignId('billing_mismatch_accepted_by')->nullable()->after('billing_mismatch_accepted_at')->constrained('users')->nullOnDelete();
            $table->text('billing_mismatch_accepted_note')->nullable()->after('billing_mismatch_accepted_by');
            $table->decimal('accepted_bills_exceed_bills_total', 12, 2)->nullable()->after('billing_mismatch_accepted_note');
            $table->decimal('accepted_bills_exceed_invoices_total', 12, 2)->nullable()->after('accepted_bills_exceed_bills_total');
            $table->timestamp('accepted_bill_after_at')->nullable()->after('accepted_bills_exceed_invoices_total');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_mismatch_accepted_by');
            $table->dropColumn([
                'billing_mismatch_accepted_at',
                'billing_mismatch_accepted_note',
                'accepted_bills_exceed_bills_total',
                'accepted_bills_exceed_invoices_total',
                'accepted_bill_after_at',
            ]);
        });
    }
};
