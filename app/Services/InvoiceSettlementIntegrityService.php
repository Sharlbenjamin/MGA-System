<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceSettlementIntegrityService
{
    public const SETTLED_STATUSES = ['Paid', 'Partial'];

    public const AMOUNT_TOLERANCE = 0.01;

    public const ISSUE_NO_TRANSACTION_LINK = 'no_transaction_link';

    public const ISSUE_AMOUNT_MISMATCH = 'amount_mismatch';

    public const ISSUE_STATUS_UNDERSTATES = 'status_understates';

    public const ISSUE_STATUS_OVERSTATES = 'status_overstates';

    public const ISSUE_LINKED_ZERO_PAYMENT = 'linked_zero_payment';

    public const ISSUE_OVER_ALLOCATED = 'over_allocated';

    public static function issueTypeLabels(): array
    {
        return [
            self::ISSUE_NO_TRANSACTION_LINK => 'Paid/Partial, no transaction link',
            self::ISSUE_AMOUNT_MISMATCH => 'Stored paid amount ≠ transaction total',
            self::ISSUE_STATUS_UNDERSTATES => 'Not marked Paid but fully paid via transactions',
            self::ISSUE_STATUS_OVERSTATES => 'Marked Paid but not fully paid',
            self::ISSUE_LINKED_ZERO_PAYMENT => 'Linked but zero payment recorded',
            self::ISSUE_OVER_ALLOCATED => 'Transaction payments exceed invoice total',
        ];
    }

    public static function issueTypeLabel(string $issueType): string
    {
        return self::issueTypeLabels()[$issueType] ?? ucfirst(str_replace('_', ' ', $issueType));
    }

    public static function pivotSumSubquerySql(): string
    {
        return '(SELECT COALESCE(SUM(invoice_transaction.amount_paid), 0) FROM invoice_transaction WHERE invoice_transaction.invoice_id = invoices.id)';
    }

    public static function applyIssuesScope(Builder $query): Builder
    {
        $pivotSum = self::pivotSumSubquerySql();

        return $query->where(function (Builder $issues) use ($pivotSum): void {
            $issues
                ->where(fn (Builder $noLink): Builder => self::applyNoTransactionLinkScope($noLink))
                ->orWhere(fn (Builder $mismatch): Builder => self::applyAmountMismatchScope($mismatch))
                ->orWhere(fn (Builder $understates): Builder => self::applyStatusUnderstatesScope($understates))
                ->orWhere(fn (Builder $overstates): Builder => self::applyStatusOverstatesScope($overstates))
                ->orWhere(fn (Builder $zeroPayment): Builder => self::applyLinkedZeroPaymentScope($zeroPayment))
                ->orWhere(fn (Builder $overAllocated): Builder => self::applyOverAllocatedScope($overAllocated));
        });
    }

    public static function applyNoTransactionLinkScope(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::SETTLED_STATUSES)
            ->doesntHave('transactions');
    }

    public static function applyAmountMismatchScope(Builder $query): Builder
    {
        return $query->whereRaw(
            'ABS(invoices.paid_amount - '.self::pivotSumSubquerySql().') > ?',
            [self::AMOUNT_TOLERANCE],
        );
    }

    public static function applyStatusUnderstatesScope(Builder $query): Builder
    {
        $pivotSum = self::pivotSumSubquerySql();

        return $query
            ->where('status', '!=', 'Paid')
            ->where('total_amount', '>', 0)
            ->whereRaw("{$pivotSum} >= invoices.total_amount - ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyStatusOverstatesScope(Builder $query): Builder
    {
        $pivotSum = self::pivotSumSubquerySql();

        return $query
            ->where('status', 'Paid')
            ->where('total_amount', '>', 0)
            ->where(function (Builder $notFullyPaid) use ($pivotSum): void {
                $notFullyPaid
                    ->whereRaw("{$pivotSum} < invoices.total_amount - ?", [self::AMOUNT_TOLERANCE])
                    ->orWhereRaw('invoices.paid_amount < invoices.total_amount - ?', [self::AMOUNT_TOLERANCE]);
            });
    }

    public static function applyLinkedZeroPaymentScope(Builder $query): Builder
    {
        $pivotSum = self::pivotSumSubquerySql();

        return $query
            ->where('total_amount', '>', 0)
            ->whereHas('transactions')
            ->whereRaw("{$pivotSum} < ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyOverAllocatedScope(Builder $query): Builder
    {
        $pivotSum = self::pivotSumSubquerySql();

        return $query
            ->where('total_amount', '>', 0)
            ->whereRaw("{$pivotSum} > invoices.total_amount + ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyIssueTypeScope(Builder $query, string $issueType): Builder
    {
        return match ($issueType) {
            self::ISSUE_NO_TRANSACTION_LINK => self::applyNoTransactionLinkScope($query),
            self::ISSUE_AMOUNT_MISMATCH => self::applyAmountMismatchScope($query),
            self::ISSUE_STATUS_UNDERSTATES => self::applyStatusUnderstatesScope($query),
            self::ISSUE_STATUS_OVERSTATES => self::applyStatusOverstatesScope($query),
            self::ISSUE_LINKED_ZERO_PAYMENT => self::applyLinkedZeroPaymentScope($query),
            self::ISSUE_OVER_ALLOCATED => self::applyOverAllocatedScope($query),
            default => $query,
        };
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
        $pivotSum ??= self::pivotSumFor($invoice);
        $paidAmount = round((float) $invoice->paid_amount, 2);
        $pivotSum = round($pivotSum, 2);
        $totalAmount = round((float) $invoice->total_amount, 2);
        $hasLinks = $invoice->relationLoaded('transactions')
            ? $invoice->transactions->isNotEmpty()
            : $invoice->transactions()->exists();

        if (! $hasLinks && in_array($invoice->status, self::SETTLED_STATUSES, true)) {
            return self::ISSUE_NO_TRANSACTION_LINK;
        }

        if ($totalAmount > 0 && $pivotSum > $totalAmount + self::AMOUNT_TOLERANCE) {
            return self::ISSUE_OVER_ALLOCATED;
        }

        if ($totalAmount > 0 && $hasLinks && $pivotSum < self::AMOUNT_TOLERANCE) {
            return self::ISSUE_LINKED_ZERO_PAYMENT;
        }

        if (abs($paidAmount - $pivotSum) > self::AMOUNT_TOLERANCE) {
            return self::ISSUE_AMOUNT_MISMATCH;
        }

        if ($invoice->status === 'Paid' && $totalAmount > 0) {
            if ($pivotSum < $totalAmount - self::AMOUNT_TOLERANCE || $paidAmount < $totalAmount - self::AMOUNT_TOLERANCE) {
                return self::ISSUE_STATUS_OVERSTATES;
            }
        }

        if ($invoice->status !== 'Paid' && $totalAmount > 0) {
            if ($pivotSum >= $totalAmount - self::AMOUNT_TOLERANCE) {
                return self::ISSUE_STATUS_UNDERSTATES;
            }
        }

        return 'unknown';
    }

    public static function pivotSumFor(Invoice $invoice): float
    {
        if (isset($invoice->pivot_paid_sum)) {
            return round((float) $invoice->pivot_paid_sum, 2);
        }

        return round($invoice->totalPaidFromTransactions(), 2);
    }

    public static function storedPivotMismatchFor(Invoice $invoice, ?float $pivotSum = null): float
    {
        $pivotSum ??= self::pivotSumFor($invoice);

        return round(abs(round((float) $invoice->paid_amount, 2) - $pivotSum), 2);
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
