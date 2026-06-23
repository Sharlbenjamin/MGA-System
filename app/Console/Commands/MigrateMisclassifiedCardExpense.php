<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionDocumentationService;
use Illuminate\Console\Command;

class MigrateMisclassifiedCardExpense extends Command
{
    protected $signature = 'transactions:migrate-card-expense-outflow
                            {--dry-run : List affected rows without updating}';

    protected $description = 'Reclassify Outflow card_expense transactions to card_provider and recalculate documentation';

    public function handle(TransactionDocumentationService $documentationService): int
    {
        $query = Transaction::query()
            ->where('documentation_category', 'card_expense')
            ->where('type', 'Outflow');

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No misclassified card_expense Outflow transactions found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would migrate {$count} transaction(s):");
            $query->orderBy('id')->each(function (Transaction $transaction): void {
                $this->line("  #{$transaction->id} — {$transaction->date?->format('Y-m-d')} — €{$transaction->amount}");
            });

            return self::SUCCESS;
        }

        $migrated = 0;

        $query->orderBy('id')->chunkById(100, function ($transactions) use ($documentationService, &$migrated): void {
            foreach ($transactions as $transaction) {
                $transaction->update(['documentation_category' => 'card_provider']);
                $documentationService->syncAndRecalculate($transaction->fresh());
                $migrated++;
            }
        });

        $this->info("Migrated {$migrated} transaction(s) from card_expense to card_provider.");

        return self::SUCCESS;
    }
}
