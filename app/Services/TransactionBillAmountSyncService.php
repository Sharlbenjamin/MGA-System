<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionBillAmountSyncService
{
    public const MAX_AMOUNT_DIFFERENCE = 100.0;

    public function canSync(Transaction $transaction): bool
    {
        if ($transaction->relationLoaded('bills')) {
            return $transaction->bills->isNotEmpty();
        }

        return $transaction->bills()->exists();
    }

    public function amountDifference(float $currentTotal, float $newTotal): float
    {
        return round(abs($newTotal - $currentTotal), 2);
    }

    public function exceedsMaxDifference(float $currentTotal, float $newTotal): bool
    {
        return $this->amountDifference($currentTotal, $newTotal) >= self::MAX_AMOUNT_DIFFERENCE;
    }

    public function validateNewTotal(Transaction $transaction, float $newTotal): void
    {
        if (! $this->canSync($transaction)) {
            throw ValidationException::withMessages([
                'new_total' => 'This transaction has no linked bills.',
            ]);
        }

        if ($newTotal <= 0) {
            throw ValidationException::withMessages([
                'new_total' => 'The new total must be greater than zero.',
            ]);
        }

        $difference = $this->amountDifference((float) $transaction->amount, $newTotal);

        if ($this->exceedsMaxDifference((float) $transaction->amount, $newTotal)) {
            throw ValidationException::withMessages([
                'new_total' => sprintf(
                    'The difference must be less than €%s (current difference: €%s).',
                    number_format(self::MAX_AMOUNT_DIFFERENCE, 2),
                    number_format($difference, 2),
                ),
            ]);
        }
    }

    /**
     * @return array<int, float> bill_id => new_total_amount
     */
    public function resolveNewBillTotals(Transaction $transaction, float $newTransactionTotal): array
    {
        $bills = $transaction->relationLoaded('bills')
            ? $transaction->bills->sortBy('id')->values()
            : $transaction->bills()->orderBy('bills.id')->get();
        $oldTransactionTotal = (float) $transaction->amount;
        $difference = round($newTransactionTotal - $oldTransactionTotal, 2);
        $count = $bills->count();

        if ($count === 1) {
            return [$bills->first()->id => round($newTransactionTotal, 2)];
        }

        $adjustments = $this->distributeAmount($difference, $count);
        $newTotals = [];

        foreach ($bills->values() as $index => $bill) {
            $newTotals[$bill->id] = round((float) $bill->total_amount + $adjustments[$index], 2);
        }

        return $newTotals;
    }

    /**
     * Split a monetary amount across a fixed number of bills, preserving cents.
     *
     * @return array<int, float>
     */
    public function distributeAmount(float $total, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [round($total, 2)];
        }

        $totalCents = (int) round($total * 100);
        $baseCents = intdiv($totalCents, $count);
        $remainderCents = $totalCents - ($baseCents * $count);

        $amounts = array_fill(0, $count, $baseCents / 100);

        $step = $remainderCents >= 0 ? 1 : -1;

        for ($index = 0; $index < abs($remainderCents); $index++) {
            $amounts[$index] += $step / 100;
        }

        return array_map(fn (float $amount): float => round($amount, 2), $amounts);
    }

    public function sync(Transaction $transaction, float $newTotal): Transaction
    {
        $this->validateNewTotal($transaction, $newTotal);

        $transaction->load('bills');
        $newBillTotals = $this->resolveNewBillTotals($transaction, $newTotal);

        DB::transaction(function () use ($transaction, $newTotal, $newBillTotals): void {
            $transaction->forceFill([
                'amount' => round($newTotal, 2),
                'updated_by' => Auth::id(),
            ])->saveQuietly();

            foreach ($transaction->bills as $bill) {
                $this->syncBill($transaction, $bill, $newBillTotals[$bill->id]);
            }
        });

        $transaction = $transaction->fresh(['bills']);

        app(TransactionSettlementService::class)->syncAfterPivotChange($transaction);

        return $transaction;
    }

    protected function syncBill(Transaction $transaction, Bill $bill, float $newBillTotal): void
    {
        $paymentDate = $bill->payment_date;

        $bill->forceFill([
            'total_amount' => $newBillTotal,
        ])->saveQuietly();

        $transaction->bills()->updateExistingPivot($bill->id, [
            'amount_paid' => $newBillTotal,
        ]);

        $paidAmount = round($bill->totalPaidFromTransactions(), 2);

        $bill->forceFill([
            'paid_amount' => $paidAmount,
            'status' => 'Paid',
            'payment_date' => $paymentDate,
        ])->saveQuietly();
    }
}
