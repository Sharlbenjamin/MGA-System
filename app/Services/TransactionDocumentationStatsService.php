<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransactionDocumentationStatsService
{
    public const INCOME_CATEGORIES = ['client_payment', 'account_feed', 'refund'];

    public const OUTFLOW_CATEGORIES = ['provider_single', 'provider_bulk', 'card_provider', 'card_expense', 'patient_refund', 'capital_return'];

    public const EXPENSE_CATEGORIES = ['expense_payment'];

    public const ALL_CATEGORIES = [
        'client_payment',
        'account_feed',
        'refund',
        'provider_single',
        'provider_bulk',
        'card_provider',
        'card_expense',
        'expense_payment',
        'patient_refund',
        'capital_return',
    ];

    /**
     * @return array<string, string>
     */
    public static function allCategoryOptions(): array
    {
        return [
            'client_payment' => 'Client Payment',
            'account_feed' => 'Account Feed',
            'refund' => 'Refund',
            'provider_single' => 'Provider Single Transfer',
            'provider_bulk' => 'Provider Bulk Transfer',
            'card_provider' => 'Card Payment (Provider)',
            'card_expense' => 'Card Payment (Expense)',
            'expense_payment' => 'Expenses Payment',
            'patient_refund' => 'Patient Refund',
            'capital_return' => 'Capital / Owner Return',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptionsFor(?string $type, ?string $relatedType): array
    {
        $all = self::allCategoryOptions();

        return match ($type) {
            'Income' => in_array($relatedType, ['Client', 'Patient'], true)
                ? array_intersect_key($all, array_flip(self::INCOME_CATEGORIES))
                : array_intersect_key($all, array_flip(self::INCOME_CATEGORIES)),
            'Outflow' => array_intersect_key($all, array_flip(self::OUTFLOW_CATEGORIES)),
            'Expense' => array_intersect_key($all, array_flip(self::EXPENSE_CATEGORIES)),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return self::allCategoryOptions();
    }

    public static function categoryLabel(string $key): string
    {
        return self::allCategoryOptions()[$key] ?? $key;
    }

    public static function resolveCategoryKey(Transaction $transaction): string
    {
        if (
            Schema::hasColumn('transactions', 'documentation_category')
            && filled($transaction->documentation_category)
        ) {
            return $transaction->documentation_category;
        }

        return self::inferCategoryKey($transaction);
    }

    public static function inferCategoryKey(Transaction $transaction): string
    {
        $billCount = $transaction->relationLoaded('bills')
            ? $transaction->bills->count()
            : $transaction->bills()->count();

        $invoiceCount = $transaction->relationLoaded('invoices')
            ? $transaction->invoices->count()
            : $transaction->invoices()->count();

        $docService = app(TransactionDocumentationService::class);

        return match (true) {
            $transaction->type === 'Income' && $invoiceCount > 0 => 'client_payment',
            $transaction->type === 'Income' => 'account_feed',
            $transaction->type === 'Expense' => 'expense_payment',
            $transaction->type === 'Outflow' && $billCount >= 2 => 'provider_bulk',
            $transaction->type === 'Outflow' && $billCount === 1 => 'provider_single',
            $transaction->type === 'Outflow' && $docService->isCardTransaction($transaction) => 'card_expense',
            $transaction->type === 'Outflow' => 'card_provider',
            default => 'account_feed',
        };
    }

    public static function defaultCategoryFor(?string $type, ?string $relatedType): ?string
    {
        $options = self::categoryOptionsFor($type, $relatedType);

        return array_key_first($options) ?: null;
    }

    /**
     * @param  array<int, int|string>  $billIds
     */
    public function applyCategory(Transaction $transaction, string $category, array $billIds = []): void
    {
        if (! array_key_exists($category, self::allCategoryOptions())) {
            throw new \InvalidArgumentException("Unknown category: {$category}");
        }

        $transaction->documentation_category = $category;

        match ($category) {
            'client_payment', 'account_feed', 'refund' => $this->applySimpleCategory($transaction, 'Income'),
            'expense_payment' => $this->applySimpleCategory($transaction, 'Expense'),
            'card_expense' => $this->applySimpleCategory($transaction, 'Outflow', detachBills: true),
            'card_provider' => $this->applySimpleCategory($transaction, 'Outflow', detachBills: false),
            'provider_single' => $this->applyBillCategory($transaction, 1, 1, $billIds),
            'provider_bulk' => $this->applyBillCategory($transaction, 2, null, $billIds),
            'patient_refund', 'capital_return' => $this->applySimpleCategory($transaction, 'Outflow', detachBills: true),
        };

        $transaction->save();
    }

    protected function applySimpleCategory(Transaction $transaction, string $type, bool $detachBills = true): void
    {
        $transaction->type = $type;

        if ($detachBills) {
            $transaction->bills()->detach();
        }
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
                'documentation_category' => "Provider Single requires exactly 1 linked bill. Currently {$count} linked.",
            ]);
        }

        if ($exactBills === null && $count < $minBills) {
            throw ValidationException::withMessages([
                'documentation_category' => "Provider Bulk requires at least {$minBills} linked bills. Currently {$count} linked.",
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
     * @param  array<int, int|string>  $invoiceIds
     */
    public function syncInvoicesWithInitialAmounts(Transaction $transaction, array $invoiceIds): void
    {
        $invoices = Invoice::query()->whereIn('id', $invoiceIds)->get();
        $sumTotals = (float) $invoices->sum('total_amount');
        $matchTotal = abs($sumTotals - (float) $transaction->amount) < 0.01;

        $sync = [];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = $invoices->firstWhere('id', (int) $invoiceId);

            if ($invoice) {
                $sync[$invoiceId] = [
                    'amount_paid' => $matchTotal ? (float) $invoice->total_amount : 0,
                ];
            }
        }

        $transaction->invoices()->sync($sync);

        foreach ($invoices as $invoice) {
            $paidAmount = $sync[$invoice->id]['amount_paid'] ?? 0;
            $invoice->paid_amount = $paidAmount;
            $invoice->save();
            $invoice->checkStatus();
        }
    }

    /**
     * @return array<int, int>
     */
    public static function normalizeLinkIds(mixed $value): array
    {
        return array_values(array_filter(array_map('intval', (array) ($value ?? []))));
    }

    public function breakdown(Builder $query): array
    {
        $result = [];

        foreach (self::ALL_CATEGORIES as $category) {
            $result[$category] = $this->countsForCategory($query, $category);
        }

        return $result;
    }

    /**
     * @return array<string, array{total: int, completed: int, uncompleted: int, data_issues: array, missing_steps: array}>
     */
    public function breakdownForBankAccount(int $bankAccountId, ?Builder $baseQuery = null): array
    {
        $query = $baseQuery ?? Transaction::query()->where('bank_account_id', $bankAccountId);
        $integrity = app(TransactionIntegrityService::class);

        $result = [];

        foreach (self::ALL_CATEGORIES as $category) {
            $stats = $this->countsForCategory(clone $query, $category);

            $stats['data_issues'] = $integrity->dataIssuesForCategory(
                $bankAccountId,
                $category,
                self::applyCategoryScope(clone $query, $category),
            );
            $stats['missing_steps'] = $this->missingStepsForCategory(clone $query, $category);

            $result[$category] = $stats;
        }

        return $result;
    }

    /**
     * @return array{total: int, completed: int, uncompleted: int}
     */
    protected function countsForCategory(Builder $query, string $category): array
    {
        $scoped = self::applyCategoryScope(clone $query, $category);

        $total = (clone $scoped)->count();
        $completed = (clone $scoped)->where('documentation_status', 'complete')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'uncompleted' => $total - $completed,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    protected function missingStepsForCategory(Builder $query, string $category): array
    {
        $scoped = self::applyCategoryScope(clone $query, $category);
        $docService = app(TransactionDocumentationService::class);

        $statusKeys = ['unlinked', 'missing_attachment', 'missing_generated_pdf', 'incomplete'];
        $steps = [];

        foreach ($statusKeys as $statusKey) {
            $count = (clone $scoped)->where('documentation_status', $statusKey)->count();

            if ($count > 0) {
                $steps[] = [
                    'key' => $statusKey,
                    'label' => $docService->formatDocumentationStatusLabel($statusKey),
                    'count' => $count,
                ];
            }
        }

        return $steps;
    }

    public static function applyWorkflowScope(Builder $query, ?string $workflow): Builder
    {
        if (! filled($workflow)) {
            return $query;
        }

        $legacyMap = [
            'income' => 'client_payment',
            'trx_out_single' => 'provider_single',
            'trx_out_bulk' => 'provider_bulk',
            'card' => 'card_expense',
            'expense' => 'expense_payment',
        ];

        $category = $legacyMap[$workflow] ?? $workflow;

        return self::applyCategoryScope($query, $category);
    }

    public static function applyCategoryScope(Builder $query, string $category): Builder
    {
        if (! Schema::hasColumn('transactions', 'documentation_category')) {
            return self::applyInferredCategoryScope(clone $query, $category);
        }

        return $query->where(function (Builder $scoped) use ($category): void {
            $scoped->where('documentation_category', $category)
                ->orWhere(function (Builder $fallback) use ($category): void {
                    $fallback->whereNull('documentation_category');
                    self::applyInferredCategoryScope($fallback, $category);
                });
        });
    }

    public static function applyInferredCategoryScope(Builder $query, string $category): Builder
    {
        match ($category) {
            'client_payment' => $query->where('type', 'Income')->has('invoices'),
            'account_feed' => $query->where('type', 'Income')->doesntHave('invoices'),
            'refund' => $query->where('type', 'Income')->doesntHave('invoices'),
            'provider_single' => $query->where('type', 'Outflow')->has('bills', '=', 1),
            'provider_bulk' => $query->where('type', 'Outflow')->has('bills', '>=', 2),
            'card_expense' => $query->where('type', 'Outflow')->doesntHave('bills'),
            'card_provider' => $query->where('type', 'Outflow')->doesntHave('bills'),
            'patient_refund' => $query->where('type', 'Outflow')->where('related_type', 'Patient'),
            'capital_return' => $query->where('type', 'Outflow')->where('related_type', 'Other'),
            'expense_payment' => $query->where('type', 'Expense'),
            default => $query->whereRaw('0 = 1'),
        };

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public static function documentationStatusKeys(): array
    {
        return [
            'unlinked',
            'missing_attachment',
            'missing_generated_pdf',
            'incomplete',
            'complete',
            'revised',
        ];
    }

    /**
     * @return array{total: int, statuses: array<int, array{key: string, label: string, count: int}>}
     */
    public function documentationStatusBreakdown(Builder $query): array
    {
        $docService = app(TransactionDocumentationService::class);
        $statuses = [];

        foreach (self::documentationStatusKeys() as $statusKey) {
            $statuses[] = [
                'key' => $statusKey,
                'label' => $docService->formatDocumentationStatusLabel($statusKey),
                'count' => (clone $query)->where('documentation_status', $statusKey)->count(),
            ];
        }

        return [
            'total' => (clone $query)->count(),
            'statuses' => $statuses,
        ];
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

    public static function uncompletedDocumentationStatuses(): array
    {
        return ['incomplete', 'unlinked', 'missing_attachment', 'missing_generated_pdf'];
    }
}
