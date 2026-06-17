<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;

class FixTransactionBankCharges extends Command
{
    protected $signature = 'transactions:fix-bank-charges
                            {--amount=2 : Bank charge value to fix}
                            {--dry-run : Preview changes without saving}
                            {--chunk=100 : Number of records per batch}';

    protected $description = 'Move bank_charges back into transaction amount (e.g. fix auto-applied €2 fees)';

    public function handle(): int
    {
        $chargeAmount = round((float) $this->option('amount'), 2);
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($chargeAmount <= 0) {
            $this->error('The --amount option must be greater than zero.');

            return self::FAILURE;
        }

        $query = Transaction::query()->where('bank_charges', $chargeAmount);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("No transactions found with bank_charges = €{$chargeAmount}.");

            return self::SUCCESS;
        }

        $this->info("Found {$total} transaction(s) with bank_charges = €".number_format($chargeAmount, 2).'.');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $previewRows = [];
        $fixed = 0;

        $query->orderBy('id')->chunkById($chunkSize, function ($transactions) use ($dryRun, &$previewRows, &$fixed) {
            foreach ($transactions as $transaction) {
                $oldAmount = (float) $transaction->amount;
                $oldCharges = (float) $transaction->bank_charges;
                $newAmount = round($oldAmount + $oldCharges, 2);

                $previewRows[] = [
                    $transaction->id,
                    $transaction->date?->format('d/m/Y') ?? '—',
                    $transaction->name,
                    number_format($oldAmount, 2),
                    number_format($newAmount, 2),
                    number_format($oldCharges, 2),
                    '0.00',
                ];

                if (! $dryRun) {
                    $transaction->amount = $newAmount;
                    $transaction->bank_charges = 0;
                    $transaction->save();
                    $fixed++;

                    $this->line(sprintf(
                        'Fixed #%d: amount %s → %s, bank_charges %s → 0.00',
                        $transaction->id,
                        number_format($oldAmount, 2),
                        number_format($newAmount, 2),
                        number_format($oldCharges, 2),
                    ));
                }
            }
        });

        if ($dryRun && $previewRows !== []) {
            $this->table(
                ['ID', 'Date', 'Name', 'Old amount', 'New amount', 'Old charges', 'New charges'],
                $previewRows,
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->info("Dry run complete. {$total} transaction(s) would be updated.");
        } else {
            $this->newLine();
            $this->info("Done. {$fixed} transaction(s) updated.");
        }

        return self::SUCCESS;
    }
}
