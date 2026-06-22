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

    public static function hasInvoiceTotalMismatch(Transaction $transaction): bool
    {
        if ($transaction->type !== 'Income') {
            return false;
        }

        if (! $transaction->relationLoaded('invoices')) {
            $transaction->load('invoices');
        }

        if ($transaction->invoices->isEmpty()) {
            return false;
        }

        $linkedPaid = self::invoicesPaidTotalFor($transaction);

        if ($linkedPaid <= 0) {
            return false;
        }

        return abs(self::invoiceAmountDifferenceFor($transaction)) >= 0.01;
    }

    public static function invoiceTotalMismatchTooltip(Transaction $transaction): ?string
    {
        if (! self::hasInvoiceTotalMismatch($transaction)) {
            return null;
        }

        $linkedPaid = self::invoicesPaidTotalFor($transaction);
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

        if ($transaction->type === 'Income' && ! $transaction->relationLoaded('invoices')) {
            $transaction->load('invoices');
        }

        if ($transaction->type === 'Income' && $transaction->invoices->isEmpty()) {
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

    public static function scopeOutflowWithoutBills(Builder $query): Builder
    {
        return $query
            ->where('transactions.type', 'Outflow')
            ->doesntHave('bills')
            ->whereNot(fn (Builder $excluded): Builder => TransactionDocumentationStatsService::applyCategoryScope($excluded, 'patient_refund'))
            ->whereNot(fn (Builder $excluded): Builder => TransactionDocumentationStatsService::applyCategoryScope($excluded, 'capital_return'))
            ->where(function (Builder $scoped): void {
                foreach (['provider_single', 'provider_bulk', 'card_provider'] as $category) {
                    $scoped->orWhere(function (Builder $categoryQuery) use ($category): void {
                        TransactionDocumentationStatsService::applyCategoryScope($categoryQuery, $category);
                    });
                }
            });
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
