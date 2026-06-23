<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionIntegrityService
{
    public static function effectiveIncomeAmountFor(Transaction $transaction): float
    {
        return (float) $transaction->amount + (float) ($transaction->bank_charges ?? 0);
    }

    public static function invoiceAmountDifferenceFor(Transaction $transaction): float
    {
        return self::effectiveIncomeAmountFor($transaction) - self::invoicesPaidTotalFor($transaction);
    }

    public static function linkedInvoicesCountFor(Transaction $transaction): int
    {
        if (isset($transaction->linked_invoices_count)) {
            return (int) $transaction->linked_invoices_count;
        }

        if ($transaction->relationLoaded('invoices')) {
            return $transaction->invoices->count();
        }

        return (int) $transaction->invoices()->count();
    }

    public static function linkedInvoicesPaidTotalFor(Transaction $transaction): float
    {
        if (isset($transaction->linked_invoices_paid_sum)) {
            return (float) $transaction->linked_invoices_paid_sum;
        }

        return self::invoicesPaidTotalFor($transaction);
    }

    public static function hasInvoiceTotalMismatch(Transaction $transaction): bool
    {
        if ($transaction->type !== 'Income') {
            return false;
        }

        if (self::linkedInvoicesCountFor($transaction) === 0) {
            return false;
        }

        $linkedPaid = self::linkedInvoicesPaidTotalFor($transaction);

        if ($linkedPaid <= 0) {
            return false;
        }

        return abs(self::effectiveIncomeAmountFor($transaction) - $linkedPaid) >= 0.01;
    }

    public static function invoiceTotalMismatchTooltip(Transaction $transaction): ?string
    {
        if (! self::hasInvoiceTotalMismatch($transaction)) {
            return null;
        }

        $linkedPaid = self::linkedInvoicesPaidTotalFor($transaction);
        $effectiveAmount = self::effectiveIncomeAmountFor($transaction);

        return sprintf(
            'Transaction €%s (amount €%s + bank charges €%s) · Linked paid €%s',
            number_format($effectiveAmount, 2),
            number_format((float) $transaction->amount, 2),
            number_format((float) ($transaction->bank_charges ?? 0), 2),
            number_format($linkedPaid, 2),
        );
    }

    public static function linkingStatusLabel(Transaction $transaction): string
    {
        if (self::hasInvoiceTotalMismatch($transaction)) {
            return 'Amount mismatch';
        }

        if ($transaction->type === 'Income' && self::linkedInvoicesCountFor($transaction) === 0) {
            return 'Unlinked';
        }

        return 'OK';
    }

    public static function linkingIssueLabel(Transaction $transaction): string
    {
        return self::linkingStatusLabel($transaction);
    }

    public static function invoicesPaidTotalFor(Transaction $transaction): float
    {
        if (! $transaction->relationLoaded('invoices')) {
            $transaction->load('invoices');
        }

        return (float) $transaction->invoices->sum(
            fn (Invoice $invoice): float => (float) ($invoice->pivot->amount_paid ?? 0)
        );
    }

    /** @deprecated Use invoicesPaidTotalFor() for linking checks */
    public static function invoicesTotalFor(Transaction $transaction): float
    {
        return self::invoicesPaidTotalFor($transaction);
    }

    public static function scopeIncomeUnlinkedOnly(Builder $query): Builder
    {
        return $query
            ->where('transactions.type', 'Income')
            ->doesntHave('invoices')
            ->where(function (Builder $categoryQuery): void {
                TransactionDocumentationStatsService::applyCategoryScope($categoryQuery, 'client_payment');
            });
    }

    public static function scopeIncomeLinkIssues(Builder $query): Builder
    {
        return $query->where('transactions.type', 'Income')->where(function (Builder $scoped): void {
            $scoped->where(fn (Builder $unlinked): Builder => self::scopeIncomeUnlinkedOnly($unlinked))
                ->orWhere(fn (Builder $mismatch): Builder => self::applyInvoiceTotalMismatchScope($mismatch));
        });
    }

    public static function scopeOutflowProviderCategories(Builder $query): Builder
    {
        return $query
            ->where('transactions.type', 'Outflow')
            ->whereNot(fn (Builder $excluded): Builder => TransactionDocumentationStatsService::applyCategoryScope($excluded, 'patient_refund'))
            ->whereNot(fn (Builder $excluded): Builder => TransactionDocumentationStatsService::applyCategoryScope($excluded, 'capital_return'))
            ->whereNot(fn (Builder $excluded): Builder => TransactionDocumentationStatsService::applyCategoryScope($excluded, 'refunded_payment'))
            ->where(function (Builder $scoped): void {
                foreach (['provider_single', 'provider_bulk', 'card_provider'] as $category) {
                    $scoped->orWhere(function (Builder $categoryQuery) use ($category): void {
                        TransactionDocumentationStatsService::applyCategoryScope($categoryQuery, $category);
                    });
                }
            });
    }

    public static function scopeOutflowWithoutProvider(Builder $query): Builder
    {
        return self::scopeOutflowProviderCategories($query)->where(function (Builder $scoped): void {
            $scoped->whereNull('transactions.related_id')
                ->orWhereNotIn('transactions.related_type', ['Provider', 'Branch']);
        });
    }

    public static function scopeOutflowWithoutBills(Builder $query): Builder
    {
        return self::scopeOutflowProviderCategories($query)->doesntHave('bills');
    }

    public static function scopeOutflowLinkIssues(Builder $query): Builder
    {
        return self::scopeOutflowProviderCategories($query)->where(function (Builder $scoped): void {
            $scoped->where(fn (Builder $withoutProvider): Builder => self::scopeOutflowWithoutProvider($withoutProvider))
                ->orWhere(fn (Builder $withoutBills): Builder => self::scopeOutflowWithoutBills($withoutBills))
                ->orWhere(fn (Builder $mismatch): Builder => self::applyBillTotalMismatchScope($mismatch));
        });
    }

    public static function billsPaidTotalFor(Transaction $transaction): float
    {
        if (! $transaction->relationLoaded('bills')) {
            $transaction->load('bills');
        }

        return (float) $transaction->bills->sum(
            fn ($bill): float => (float) ($bill->pivot->amount_paid ?? 0)
        );
    }

    public static function billAmountDifferenceFor(Transaction $transaction): float
    {
        return (float) $transaction->amount - self::billsPaidTotalFor($transaction);
    }

    public static function hasBillTotalMismatch(Transaction $transaction): bool
    {
        if ($transaction->type !== 'Outflow') {
            return false;
        }

        if (self::linkedBillsCountFor($transaction) === 0) {
            return false;
        }

        $linkedPaid = self::billsPaidTotalFor($transaction);

        if ($linkedPaid <= 0) {
            return false;
        }

        return abs((float) $transaction->amount - $linkedPaid) >= 0.01;
    }

    public static function linkedBillsCountFor(Transaction $transaction): int
    {
        if (isset($transaction->linked_bills_count)) {
            return (int) $transaction->linked_bills_count;
        }

        if ($transaction->relationLoaded('bills')) {
            return $transaction->bills->count();
        }

        return (int) $transaction->bills()->count();
    }

    public static function billTotalMismatchTooltip(Transaction $transaction): ?string
    {
        if (! self::hasBillTotalMismatch($transaction)) {
            return null;
        }

        $linkedPaid = self::billsPaidTotalFor($transaction);

        return sprintf(
            'Transaction €%s · Linked bills paid €%s',
            number_format((float) $transaction->amount, 2),
            number_format($linkedPaid, 2),
        );
    }

    public static function outflowLinkingIssueLabel(Transaction $transaction): string
    {
        $issues = [];

        if (! in_array($transaction->related_type, ['Provider', 'Branch'], true) || ! $transaction->related_id) {
            $issues[] = 'No provider';
        }

        if (self::linkedBillsCountFor($transaction) === 0) {
            $issues[] = 'No bills';
        } elseif (self::hasBillTotalMismatch($transaction)) {
            $issues[] = 'Amount mismatch';
        }

        return $issues !== [] ? implode(', ', $issues) : 'OK';
    }

    public static function countTransactionsWithBillTotalMismatch(Builder $query): int
    {
        return (clone $query)
            ->where('transactions.type', 'Outflow')
            ->whereHas('bills')
            ->whereRaw(self::billTotalMismatchSql())
            ->count();
    }

    public static function applyBillTotalMismatchScope(Builder $query): Builder
    {
        return $query
            ->where('transactions.type', 'Outflow')
            ->whereHas('bills')
            ->whereRaw(self::billTotalMismatchSql());
    }

    protected static function billTotalMismatchSql(): string
    {
        return 'ABS(transactions.amount - (
                SELECT COALESCE(SUM(bill_transaction.amount_paid), 0)
                FROM bill_transaction
                WHERE bill_transaction.transaction_id = transactions.id
            )) >= 0.01';
    }

    public function scopedInvoicesForBankAccount(int $bankAccountId): Builder
    {
        return Invoice::query()->where(function (Builder $query) use ($bankAccountId): void {
            $query->where('bank_account_id', $bankAccountId)
                ->orWhereHas('transactions', fn (Builder $t) => $t->where('bank_account_id', $bankAccountId));
        });
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function dataIssuesForCategory(int $bankAccountId, string $category, Builder $categoryTransactionQuery): array
    {
        $issues = [];

        if ($category === 'client_payment') {
            $paidNoTrx = (clone $this->scopedInvoicesForBankAccount($bankAccountId))
                ->where('status', 'Paid')
                ->doesntHave('transactions')
                ->count();

            if ($paidNoTrx > 0) {
                $issues[] = [
                    'key' => 'paid_no_transaction',
                    'label' => 'Paid, no transaction linked',
                    'count' => $paidNoTrx,
                ];
            }

            $paidMismatch = (clone $this->scopedInvoicesForBankAccount($bankAccountId))
                ->where('status', 'Paid')
                ->whereRaw('ABS(COALESCE(paid_amount, 0) - total_amount) >= 0.01')
                ->count();

            if ($paidMismatch > 0) {
                $issues[] = [
                    'key' => 'paid_amount_mismatch',
                    'label' => 'Paid, amount does not match total',
                    'count' => $paidMismatch,
                ];
            }

            $trxMismatch = self::countTransactionsWithInvoiceTotalMismatch($categoryTransactionQuery);

            if ($trxMismatch > 0) {
                $issues[] = [
                    'key' => 'transaction_invoice_total_mismatch',
                    'label' => 'Transaction / invoice total mismatch',
                    'count' => $trxMismatch,
                ];
            }
        }

        return $issues;
    }

    public static function countTransactionsWithInvoiceTotalMismatch(Builder $query): int
    {
        return (clone $query)
            ->where('transactions.type', 'Income')
            ->whereHas('invoices')
            ->whereRaw(self::invoiceTotalMismatchSql())
            ->count();
    }

    public static function applyInvoiceTotalMismatchScope(Builder $query): Builder
    {
        return $query
            ->where('transactions.type', 'Income')
            ->whereHas('invoices')
            ->whereRaw(self::invoiceTotalMismatchSql());
    }

    protected static function invoiceTotalMismatchSql(): string
    {
        return 'ABS((transactions.amount + COALESCE(transactions.bank_charges, 0)) - (
                SELECT COALESCE(SUM(invoice_transaction.amount_paid), 0)
                FROM invoice_transaction
                WHERE invoice_transaction.transaction_id = transactions.id
            )) >= 0.01';
    }

    public static function applyPaidInvoiceAmountMismatchScope(Builder $query): Builder
    {
        return $query
            ->where('transactions.type', 'Income')
            ->whereHas('invoices', fn (Builder $q) => $q
                ->where('status', 'Paid')
                ->whereRaw('ABS(COALESCE(paid_amount, 0) - total_amount) >= 0.01'));
    }
}
