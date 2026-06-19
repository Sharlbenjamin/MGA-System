<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionIntegrityService
{
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

        $invoiceTotal = (float) $transaction->invoices->sum('total_amount');

        if ($invoiceTotal <= 0) {
            return false;
        }

        return abs((float) $transaction->amount - $invoiceTotal) >= 0.01;
    }

    public static function invoiceTotalMismatchTooltip(Transaction $transaction): ?string
    {
        if (! self::hasInvoiceTotalMismatch($transaction)) {
            return null;
        }

        $invoiceTotal = (float) $transaction->invoices->sum('total_amount');

        return sprintf(
            'Transaction €%s · Invoices total €%s',
            number_format((float) $transaction->amount, 2),
            number_format($invoiceTotal, 2),
        );
    }

    public static function linkingStatusLabel(Transaction $transaction): string
    {
        return self::hasInvoiceTotalMismatch($transaction) ? 'Amount mismatch' : 'OK';
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
            ->where('type', 'Income')
            ->whereHas('invoices')
            ->whereRaw('ABS(transactions.amount - (
                SELECT COALESCE(SUM(invoices.total_amount), 0)
                FROM invoice_transaction
                JOIN invoices ON invoices.id = invoice_transaction.invoice_id
                WHERE invoice_transaction.transaction_id = transactions.id
            )) >= 0.01')
            ->count();
    }

    public static function applyInvoiceTotalMismatchScope(Builder $query): Builder
    {
        return $query
            ->where('type', 'Income')
            ->whereHas('invoices')
            ->whereRaw('ABS(transactions.amount - (
                SELECT COALESCE(SUM(invoices.total_amount), 0)
                FROM invoice_transaction
                JOIN invoices ON invoices.id = invoice_transaction.invoice_id
                WHERE invoice_transaction.transaction_id = transactions.id
            )) >= 0.01');
    }

    public static function applyPaidInvoiceAmountMismatchScope(Builder $query): Builder
    {
        return $query
            ->where('type', 'Income')
            ->whereHas('invoices', fn (Builder $q) => $q
                ->where('status', 'Paid')
                ->whereRaw('ABS(COALESCE(paid_amount, 0) - total_amount) >= 0.01'));
    }
}
