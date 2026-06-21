<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceSettlementIntegrityService
{
    public const SETTLED_STATUSES = ['Paid', 'Partial'];

    public const AMOUNT_TOLERANCE = 0.01;

    public static function pivotSumSubquerySql(): string
    {
        return '(SELECT COALESCE(SUM(invoice_transaction.amount_paid), 0) FROM invoice_transaction WHERE invoice_transaction.invoice_id = invoices.id)';
    }

    public static function applyIssuesScope(Builder $query): Builder
    {
        return $query->where(function (Builder $issues): void {
            $issues
                ->where(function (Builder $noLink): void {
                    $noLink
                        ->whereIn('status', self::SETTLED_STATUSES)
                        ->doesntHave('transactions');
                })
                ->orWhereRaw(
                    'ABS(invoices.paid_amount - '.self::pivotSumSubquerySql().') > ?',
                    [self::AMOUNT_TOLERANCE],
                );
        });
    }

    public static function applyNoTransactionLinkScope(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::SETTLED_STATUSES)
            ->doesntHave('transactions');
    }

    public static function settlementIssueCount(): int
    {
        return self::issuesQuery()->count();
    }

    public static function issuesQuery(): Builder
    {
        return self::applyIssuesScope(Invoice::query());
    }

    /**
     * @return Collection<int, Invoice>
     */
    public static function findSettlementIssues(): Collection
    {
        $pivotSum = self::pivotSumSubquerySql();

        return self::issuesQuery()
            ->select('invoices.*')
            ->selectRaw("{$pivotSum} as pivot_paid_sum")
            ->selectRaw('(SELECT COUNT(*) FROM invoice_transaction WHERE invoice_transaction.invoice_id = invoices.id) as linked_transaction_count')
            ->orderBy('invoices.id')
            ->get();
    }

    public static function describeIssue(Invoice $invoice, ?float $pivotSum = null): string
    {
        $pivotSum ??= $invoice->totalPaidFromTransactions();
        $hasLinks = $invoice->transactions()->exists();

        if (! $hasLinks && in_array($invoice->status, self::SETTLED_STATUSES, true)) {
            return 'no_transaction_link';
        }

        if (abs((float) $invoice->paid_amount - $pivotSum) > self::AMOUNT_TOLERANCE) {
            return 'amount_mismatch';
        }

        return 'unknown';
    }

    public static function pivotSumFor(Invoice $invoice): float
    {
        return round($invoice->totalPaidFromTransactions(), 2);
    }

    public static function recalculateIssue(Invoice $invoice): Invoice
    {
        $invoice->recalculatePaidAmountFromTransactions();

        return $invoice->fresh();
    }

    /**
     * @param  Collection<int, Invoice>|iterable<Invoice>  $invoices
     */
    public static function recalculateIssues(iterable $invoices): int
    {
        $count = 0;

        foreach ($invoices as $invoice) {
            self::recalculateIssue($invoice);
            $count++;
        }

        return $count;
    }
}
