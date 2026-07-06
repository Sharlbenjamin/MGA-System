<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gops', function (Blueprint $table) {
            $table->foreignId('provider_branch_id')
                ->nullable()
                ->after('file_id')
                ->constrained('provider_branches')
                ->nullOnDelete();

            $table->decimal('offered_cost', 15, 2)->nullable()->after('amount');
            $table->decimal('file_fee', 15, 2)->nullable()->after('offered_cost');
            $table->text('notes')->nullable()->after('file_fee');
        });

        DB::table('gops')
            ->where('type', 'In')
            ->orderBy('id')
            ->chunkById(100, function ($gops): void {
                foreach ($gops as $gop) {
                    $status = match ($gop->status) {
                        'Not Sent' => 'Draft',
                        'Sent', 'Updated', 'Received' => 'Offered',
                        'Cancelled' => 'Rejected',
                        default => in_array($gop->status, ['Draft', 'Offered', 'Accepted', 'Rejected'], true)
                            ? $gop->status
                            : 'Draft',
                    };

                    DB::table('gops')
                        ->where('id', $gop->id)
                        ->update([
                            'offered_cost' => $gop->amount,
                            'file_fee' => 0,
                            'status' => $status,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('gops')
            ->where('type', 'In')
            ->orderBy('id')
            ->each(function (object $gop): void {
                $status = match ($gop->status) {
                    'Draft' => 'Not Sent',
                    'Offered' => 'Sent',
                    'Accepted' => 'Sent',
                    'Rejected' => 'Cancelled',
                    default => $gop->status,
                };

                $amount = $gop->offered_cost ?? $gop->amount;

                DB::table('gops')
                    ->where('id', $gop->id)
                    ->update([
                        'amount' => $amount,
                        'status' => $status,
                    ]);
            });

        Schema::table('gops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_branch_id');
            $table->dropColumn(['offered_cost', 'file_fee', 'notes']);
        });
    }
};
