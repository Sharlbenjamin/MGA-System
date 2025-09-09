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
        // Add invoice_document_path to invoices table
        if (!Schema::hasColumn('invoices', 'invoice_document_path')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_document_path')->nullable()->after('invoice_google_link');
            });
        }

        // Add bill_document_path to bills table
        if (!Schema::hasColumn('bills', 'bill_document_path')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('bill_document_path')->nullable()->after('bill_google_link');
            });
        }

        // Add document_path to medical_reports table
        if (!Schema::hasColumn('medical_reports', 'document_path')) {
            Schema::table('medical_reports', function (Blueprint $table) {
                $table->string('document_path')->nullable()->after('advice');
            });
        }

        // Add document_path to prescriptions table
        if (!Schema::hasColumn('prescriptions', 'document_path')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->string('document_path')->nullable()->after('date');
            });
        }

        // Add document_path to gops table (optional)
        if (!Schema::hasColumn('gops', 'document_path')) {
            Schema::table('gops', function (Blueprint $table) {
                $table->string('document_path')->nullable()->after('gop_google_drive_link');
            });
        }

        // Note: transactions table already has attachment_path column, so skipping it
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove invoice_document_path from invoices table
        if (Schema::hasColumn('invoices', 'invoice_document_path')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_document_path');
            });
        }

        // Remove bill_document_path from bills table
        if (Schema::hasColumn('bills', 'bill_document_path')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('bill_document_path');
            });
        }

        // Remove document_path from medical_reports table
        if (Schema::hasColumn('medical_reports', 'document_path')) {
            Schema::table('medical_reports', function (Blueprint $table) {
                $table->dropColumn('document_path');
            });
        }

        // Remove document_path from prescriptions table
        if (Schema::hasColumn('prescriptions', 'document_path')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropColumn('document_path');
            });
        }

        // Remove document_path from gops table
        if (Schema::hasColumn('gops', 'document_path')) {
            Schema::table('gops', function (Blueprint $table) {
                $table->dropColumn('document_path');
            });
        }
    }
};
