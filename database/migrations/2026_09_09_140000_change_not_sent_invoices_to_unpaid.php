<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where('status', 'Not Sent')
            ->update(['status' => 'Unpaid']);
    }

    public function down(): void
    {
        // Not reversible: Unpaid was already a valid invoice status.
    }
};
