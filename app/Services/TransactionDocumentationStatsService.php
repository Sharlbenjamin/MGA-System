<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TransactionDocumentationStatsService
{
    public function breakdown(Builder $query): array
    {
        return [
            'trx_in' => $this->countsFor($query, fn (Builder $scoped) => $scoped->where('type', 'Income')),
            'trx_out_bulk' => $this->countsFor($query, fn (Builder $scoped) => $scoped->where('type', 'Outflow')->has('bills', '>=', 2)),
            'trx_out_single' => $this->countsFor($query, fn (Builder $scoped) => $scoped->where('type', 'Outflow')->has('bills', '=', 1)),
            'exp' => $this->countsFor($query, fn (Builder $scoped) => $scoped->where('type', 'Expense')),
            'card' => $this->countsFor($query, fn (Builder $scoped) => $scoped->where('type', 'Outflow')->doesntHave('bills')),
        ];
    }

    public static function applyWorkflowScope(Builder $query, ?string $workflow): Builder
    {
        return match ($workflow) {
            'income' => $query->where('type', 'Income'),
            'trx_out_single' => $query->where('type', 'Outflow')->has('bills', '=', 1),
            'trx_out_bulk' => $query->where('type', 'Outflow')->has('bills', '>=', 2),
            'card' => $query->where('type', 'Outflow')->doesntHave('bills'),
            'expense' => $query->where('type', 'Expense'),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            'income' => 'Trx In',
            'trx_out_bulk' => 'Trx Out Bulk',
            'trx_out_single' => 'Trx Out Single',
            'expense' => 'Trx Out Exp',
            'card' => 'Card',
        ];
    }

    public static function categoryLabel(string $key): string
    {
        return self::categoryOptions()[$key] ?? $key;
    }

    public static function resolveCategoryKey(Transaction $transaction): string
    {
        $billCount = $transaction->relationLoaded('bills')
            ? $transaction->bills->count()
            : $transaction->bills()->count();

        return match (true) {
            $transaction->type === 'Income' => 'income',
            $transaction->type === 'Expense' => 'expense',
            $transaction->type === 'Outflow' && $billCount >= 2 => 'trx_out_bulk',
            $transaction->type === 'Outflow' && $billCount === 1 => 'trx_out_single',
            $transaction->type === 'Outflow' => 'card',
            default => 'income',
        };
    }

    /**
     * @param  array<int, int|string>  $billIds
     */
    public function applyCategory(Transaction $transaction, string $category, array $billIds = []): void
    {
        if (! array_key_exists($category, self::categoryOptions())) {
            throw new \InvalidArgumentException("Unknown category: {$category}");
        }

        match ($category) {
            'income' => $this->applySimpleCategory($transaction, 'Income'),
            'expense' => $this->applySimpleCategory($transaction, 'Expense'),
            'card' => $this->applySimpleCategory($transaction, 'Outflow'),
            'trx_out_single' => $this->applyBillCategory($transaction, 1, 1, $billIds),
            'trx_out_bulk' => $this->applyBillCategory($transaction, 2, null, $billIds),
        };

        $transaction->save();
    }

    protected function applySimpleCategory(Transaction $transaction, string $type): void
    {
        $transaction->type = $type;
        $transaction->bills()->detach();
    }

    /**
     * @param  array<int, int|string>  $billIds
     */
    protected function applyBillCategory(
        Transaction $transaction,
        int $minBills,
        ?int $exactBills,
        array $billIds,
    ): void {
        $transaction->type = 'Outflow';
        $this->syncBills($transaction, $billIds);

        $count = $transaction->bills()->count();

        if ($exactBills !== null && $count !== $exactBills) {
            throw ValidationException::withMessages([
                'documentation_category' => "Trx Out Single requires exactly 1 linked bill. Currently {$count} linked.",
            ]);
        }

        if ($exactBills === null && $count < $minBills) {
            throw ValidationException::withMessages([
                'documentation_category' => "Trx Out Bulk requires at least {$minBills} linked bills. Currently {$count} linked.",
            ]);
        }
    }

    /**
     * @param  array<int, int|string>  $billIds
     */
    public function syncBills(Transaction $transaction, array $billIds): void
    {
        $sync = [];

        foreach ($billIds as $billId) {
            $bill = Bill::find($billId);

            if ($bill) {
                $sync[$billId] = ['amount_paid' => $bill->total_amount];
            }
        }

        $transaction->bills()->sync($sync);
    }

    /**
     * @param  array<int, int|string>  $invoiceIds
     */
    public function syncInvoices(Transaction $transaction, array $invoiceIds): void
    {
        $sync = [];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);

            if ($invoice) {
                $sync[$invoiceId] = ['amount_paid' => $invoice->total_amount];
            }
        }

        $transaction->invoices()->sync($sync);
    }

    /**
     * @return array<int, int>
     */
    public static function normalizeLinkIds(mixed $value): array
    {
        return array_values(array_filter(array_map('intval', (array) ($value ?? []))));
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return array{total: int, completed: int, uncompleted: int}
     */
    protected function countsFor(Builder $query, callable $scope): array
    {
        $scoped = $scope(clone $query);

        $total = (clone $scoped)->count();
        $completed = (clone $scoped)->where('documentation_status', 'complete')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'uncompleted' => $total - $completed,
        ];
    }
}
