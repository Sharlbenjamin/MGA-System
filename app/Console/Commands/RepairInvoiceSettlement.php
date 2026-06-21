<?php

namespace App\Console\Commands;

use App\Services\InvoiceSettlementIntegrityService;
use Illuminate\Console\Command;

class RepairInvoiceSettlement extends Command
{
    protected $signature = 'invoices:repair-paid-from-transactions
                            {--dry-run : List settlement issues without updating invoices}
                            {--apply : Recalculate paid_amount and status from transaction links}';

    protected $description = 'Find and repair invoices marked Paid/Partial that do not match transaction links';

    public function handle(InvoiceSettlementIntegrityService $integrity): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;

        if ($apply && $dryRun) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $issues = $integrity::findSettlementIssues();

        if ($issues->isEmpty()) {
            $this->info('No invoice settlement issues found.');

            return self::SUCCESS;
        }

        $rows = $issues->map(function ($invoice) use ($integrity) {
            $pivotSum = (float) ($invoice->pivot_paid_sum ?? $integrity::pivotSumFor($invoice));
            $linkedCount = (int) ($invoice->linked_transaction_count ?? $invoice->transactions()->count());

            return [
                $invoice->id,
                $invoice->name,
                $invoice->status,
                number_format((float) $invoice->paid_amount, 2),
                number_format($pivotSum, 2),
                $linkedCount,
                $integrity::describeIssue($invoice, $pivotSum),
            ];
        })->all();

        $this->info('Invoice settlement issues: '.$issues->count());
        $this->table(
            ['ID', 'Name', 'Status', 'paid_amount', 'pivot_sum', 'tx_count', 'issue'],
            array_slice($rows, 0, 25),
        );

        if (count($rows) > 25) {
            $this->comment('... and '.(count($rows) - 25).' more.');
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run — no changes made. Re-run with --apply to recalculate affected invoices.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Recalculate '.$issues->count().' invoice(s) from transaction links?', true)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        $updated = $integrity::recalculateIssues($issues);

        $remaining = $integrity::settlementIssueCount();

        $this->newLine();
        $this->info("Recalculated {$updated} invoice(s). Remaining issues: {$remaining}.");

        return $remaining > 0 ? self::FAILURE : self::SUCCESS;
    }
}
