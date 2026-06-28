<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('invoice_file_fee_strategy', 32)->default('tier')->after('invoice_cc_emails');
            $table->string('invoice_template', 32)->default('itemized')->after('invoice_file_fee_strategy');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['invoice_file_fee_strategy', 'invoice_template']);
        });
    }
};
