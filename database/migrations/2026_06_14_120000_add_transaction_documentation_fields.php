<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_duplicates')->default(0);
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('notes');
            $table->string('documentation_status')->default('incomplete')->after('status');
            $table->string('trx_out_pdf_path')->nullable()->after('attachment_path');
            $table->string('trx_in_pdf_path')->nullable()->after('trx_out_pdf_path');
            $table->foreignId('import_batch_id')->nullable()->after('trx_in_pdf_path')
                ->constrained('transaction_import_batches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('import_batch_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();

            $table->index('documentation_status');
            $table->index(['date', 'amount', 'type']);
        });

        Schema::create('transaction_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_attachments');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'reference',
                'documentation_status',
                'trx_out_pdf_path',
                'trx_in_pdf_path',
                'import_batch_id',
                'created_by',
                'updated_by',
            ]);
        });

        Schema::dropIfExists('transaction_import_batches');
    }
};
