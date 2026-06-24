<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('documentation_skipped_at')->nullable()->after('documentation_category');
            $table->foreignId('documentation_skipped_by')->nullable()->after('documentation_skipped_at')
                ->constrained('users')->nullOnDelete();
            $table->text('documentation_skip_reason')->nullable()->after('documentation_skipped_by');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['documentation_skipped_by']);
            $table->dropColumn([
                'documentation_skipped_at',
                'documentation_skipped_by',
                'documentation_skip_reason',
            ]);
        });
    }
};
