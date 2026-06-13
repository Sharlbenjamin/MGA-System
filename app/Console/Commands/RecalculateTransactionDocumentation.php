<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionDocumentationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecalculateTransactionDocumentation extends Command
{
    protected $signature = 'transactions:recalculate-documentation {--chunk=100 : Number of records per batch}';

    protected $description = 'Recalculate documentation_status for all transactions';

    public function handle(TransactionDocumentationService $documentationService): int
    {
        if (! Schema::hasColumn('transactions', 'documentation_status')) {
            $this->error('The documentation_status column does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');
        $counts = [];
        $processed = 0;

        $this->info('Recalculating transaction documentation status...');

        Transaction::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($transactions) use ($documentationService, &$counts, &$processed) {
                foreach ($transactions as $transaction) {
                    $documentationService->syncAndRecalculate($transaction);
                    $transaction->refresh();

                    $status = $transaction->documentation_status ?? 'unknown';
                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                    $processed++;
                }

                $this->line("Processed {$processed} transactions...");
            });

        $this->newLine();
        $this->info("Finished. {$processed} transactions recalculated.");
        $this->table(
            ['Documentation status', 'Count'],
            collect($counts)->sortKeys()->map(fn (int $count, string $status) => [$status, $count])->values()->all()
        );

        return self::SUCCESS;
    }
}
