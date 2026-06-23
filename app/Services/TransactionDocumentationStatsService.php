<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransactionDocumentationStatsService
{
    public const INCOME_CATEGORIES = ['client_payment', 'account_feed', 'refund'];

    public const OUTFLOW_CATEGORIES = ['provider_single', 'provider_bulk', 'card_provider', 'patient_refund', 'capital_return', 'refunded_payment'];

    public const EXPENSE_CATEGORIES = ['expense_payment', 'refunded_payment', 'card_expense'];

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
        'refunded_payment',
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
            'refunded_payment' => 'Refunded Payment',
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
        if (filled($transaction->documentation_category)) {
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
            $transaction->type === 'Outflow' && $docService->isCardPayment($transaction) => 'card_provider',
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
            'card_expense' => $this->applySimpleCategory($transaction, 'Expense', detachBills: true),
            'card_provider' => $this->applySimpleCategory($transaction, 'Outflow', detachBills: false),
            'provider_single' => $this->applyBillCategory($transaction, 1, 1, $billIds),
            'provider_bulk' => $this->applyBillCategory($transaction, 2, null, $billIds),
            'patient_refund', 'capital_return' => $this->applySimpleCategory($transaction, 'Outflow', detachBills: true),
            'refunded_payment' => $this->applyRefundedPaymentCategory($transaction),
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

    protected function applyRefundedPaymentCategory(Transaction $transaction): void
    {
        if (! in_array($transaction->type, ['Outflow', 'Expense'], true)) {
            throw ValidationException::withMessages([
                'documentation_category' => 'Refunded Payment applies only to Outflow or Expense transactions.',
            ]);
        }

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
        $previousIds = $transaction->bills()->pluck('bills.id')->all();
        $sync = $this->resolveBillSyncAmounts($transaction, $billIds);
        $transaction->bills()->sync($sync);

        $affectedIds = array_values(array_unique([...$previousIds, ...array_keys($sync)]));

        if ($affectedIds === []) {
            return;
        }

        foreach (Bill::query()->whereIn('id', $affectedIds)->get() as $bill) {
            $bill->recalculatePaidAmountFromTransactions();
        }
    }

    /**
     * @param  array<int, int|string>  $billIds
     * @return array<int, array{amount_paid: float}>
     */
    public function resolveBillSyncAmounts(Transaction $transaction, array $billIds): array
    {
        $billIds = self::normalizeLinkIds($billIds);
        $bills = Bill::query()->whereIn('id', $billIds)->get()->keyBy('id');
        $existing = $transaction->exists
            ? $transaction->bills()->get()->keyBy('id')
            : collect();

        $sync = [];

        foreach ($billIds as $billId) {
            $bill = $bills->get($billId);

            if (! $bill) {
                continue;
            }

            if ($existing->has($billId)) {
                $sync[$billId] = [
                    'amount_paid' => (float) ($existing[$billId]->pivot->amount_paid ?? 0),
                ];

                continue;
            }

            $sync[$billId] = [
                'amount_paid' => min($bill->remainingBalance(), (float) $bill->total_amount),
            ];
        }

        return $sync;
    }

    /**
     * @param  array<int, int|string>  $invoiceIds
     */
    public function syncInvoices(Transaction $transaction, array $invoiceIds): void
    {
        $this->applyInvoiceSync($transaction, $invoiceIds, prefillWhenTotalsMatch: false);
    }

    /**
     * @param  array<int, int|string>  $invoiceIds
     */
    public function syncInvoicesWithInitialAmounts(Transaction $transaction, array $invoiceIds): void
    {
        $this->applyInvoiceSync($transaction, $invoiceIds, prefillWhenTotalsMatch: true);
    }

    /**
     * @param  array<int, int|string>  $invoiceIds
     * @return array<int, array{amount_paid: float}>
     */
    public function resolveInvoiceSyncAmounts(Transaction $transaction, array $invoiceIds, bool $prefillWhenTotalsMatch = false): array
    {
        $invoiceIds = self::normalizeLinkIds($invoiceIds);
        $invoices = Invoice::query()->whereIn('id', $invoiceIds)->get()->keyBy('id');
        $existing = $transaction->exists
            ? $transaction->invoices()->get()->keyBy('id')
            : collect();

        $sync = [];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = $invoices->get($invoiceId);

            if (! $invoice) {
                continue;
            }

            if ($existing->has($invoiceId)) {
                $sync[$invoiceId] = [
                    'amount_paid' => (float) ($existing[$invoiceId]->pivot->amount_paid ?? 0),
                ];

                continue;
            }

            if (! $prefillWhenTotalsMatch) {
                $sync[$invoiceId] = [
                    'amount_paid' => min($invoice->remainingBalance(), (float) $invoice->total_amount),
                ];
            }
        }

        if ($prefillWhenTotalsMatch) {
            $sumRemaining = (float) $invoices->sum(fn (Invoice $invoice): float => $invoice->remainingBalance());
            $matchTotal = abs(
                $sumRemaining - TransactionIntegrityService::effectiveIncomeAmountFor($transaction)
            ) < 0.01;

            foreach ($invoiceIds as $invoiceId) {
                if ($existing->has($invoiceId)) {
                    continue;
                }

                $invoice = $invoices->get($invoiceId);

                if (! $invoice) {
                    continue;
                }

                $sync[$invoiceId] = [
                    'amount_paid' => $matchTotal ? $invoice->remainingBalance() : 0,
                ];
            }
        }

        return $sync;
    }

    /**
     * @param  array<int, int|string>  $invoiceIds
     */
    protected function applyInvoiceSync(Transaction $transaction, array $invoiceIds, bool $prefillWhenTotalsMatch): void
    {
        $previousIds = $transaction->invoices()->pluck('invoices.id')->all();
        $sync = $this->resolveInvoiceSyncAmounts($transaction, $invoiceIds, $prefillWhenTotalsMatch);
        $transaction->invoices()->sync($sync);

        $affectedIds = array_values(array_unique([...$previousIds, ...array_keys($sync)]));

        if ($affectedIds === []) {
            return;
        }

        foreach (Invoice::query()->whereIn('id', $affectedIds)->get() as $invoice) {
            $invoice->recalculatePaidAmountFromTransactions();
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
        if ($baseQuery !== null) {
            return $this->computeBreakdownFromQuery($bankAccountId, $baseQuery);
        }

        return Cache::remember(
            self::breakdownCacheKey($bankAccountId),
            300,
            fn (): array => $this->computeBreakdownFromQuery(
                $bankAccountId,
                Transaction::query()->where('bank_account_id', $bankAccountId),
            ),
        );
    }

    public static function forgetBankAccountCache(int $bankAccountId): void
    {
        Cache::forget(self::breakdownCacheKey($bankAccountId));
        Cache::forget(self::statusCacheKey($bankAccountId));
        Cache::forget(self::summaryCacheKey($bankAccountId));
    }

    protected static function breakdownCacheKey(int $bankAccountId): string
    {
        return "txn_doc_stats:breakdown:{$bankAccountId}";
    }

    protected static function statusCacheKey(int $bankAccountId): string
    {
        return "txn_doc_stats:status:{$bankAccountId}";
    }

    /**
     * @return array{total: int, statuses: array<int, array{key: string, label: string, count: int}>}
     */
    public function documentationStatusBreakdownForBankAccount(int $bankAccountId): array
    {
        return Cache::remember(
            self::statusCacheKey($bankAccountId),
            300,
            fn (): array => $this->documentationStatusBreakdown(
                Transaction::query()->where('bank_account_id', $bankAccountId),
            ),
        );
    }

    /**
     * @return array<string, array{total: int, completed: int, uncompleted: int, data_issues: array, missing_steps: array}>
     */
    protected function computeBreakdownFromQuery(int $bankAccountId, Builder $baseQuery): array
    {
        $matrix = $this->fetchCategoryStatusMatrix($bankAccountId, $baseQuery);
        $integrity = app(TransactionIntegrityService::class);

        $dataIssuesByCategory = [
            'client_payment' => $integrity->dataIssuesForCategory(
                $bankAccountId,
                'client_payment',
                self::applyCategoryScope(clone $baseQuery, 'client_payment'),
            ),
        ];

        return self::buildBreakdownFromAggregates($matrix, $dataIssuesByCategory);
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function fetchCategoryStatusMatrix(int $bankAccountId, Builder $baseQuery): array
    {
        $matrix = array_fill_keys(self::ALL_CATEGORIES, []);

        $explicitCounts = (clone $baseQuery)
            ->whereNotNull('documentation_category')
            ->selectRaw('documentation_category as category, documentation_status, COUNT(*) as aggregate')
            ->groupBy('documentation_category', 'documentation_status')
            ->get();

        foreach ($explicitCounts as $row) {
            $category = (string) $row->category;

            if (! array_key_exists($category, $matrix)) {
                continue;
            }

            $matrix[$category][(string) $row->documentation_status] = (int) $row->aggregate;
        }

        $nullCategoryBase = (clone $baseQuery)->whereNull('documentation_category');

        foreach (self::ALL_CATEGORIES as $category) {
            $scoped = self::applyInferredCategoryScope(clone $nullCategoryBase, $category);
            $statusCounts = $scoped
                ->selectRaw('documentation_status, COUNT(*) as aggregate')
                ->groupBy('documentation_status')
                ->pluck('aggregate', 'documentation_status');

            foreach ($statusCounts as $status => $count) {
                $matrix[$category][(string) $status] = ($matrix[$category][(string) $status] ?? 0) + (int) $count;
            }
        }

        return $matrix;
    }

    /**
     * @param  array<string, array<string, int>>  $matrix
     * @param  array<string, array<int, array{key: string, label: string, count: int}>>  $dataIssuesByCategory
     * @return array<string, array{total: int, completed: int, uncompleted: int, data_issues: array, missing_steps: array}>
     */
    public static function buildBreakdownFromAggregates(array $matrix, array $dataIssuesByCategory = []): array
    {
        $docService = app(TransactionDocumentationService::class);
        $stepKeys = ['unlinked', 'missing_attachment', 'missing_generated_pdf', 'incomplete'];
        $result = [];

        foreach (self::ALL_CATEGORIES as $category) {
            $statusCounts = $matrix[$category] ?? [];
            $total = (int) array_sum($statusCounts);
            $completed = (int) ($statusCounts['complete'] ?? 0);
            $missingSteps = [];

            foreach ($stepKeys as $statusKey) {
                $count = (int) ($statusCounts[$statusKey] ?? 0);

                if ($count > 0) {
                    $missingSteps[] = [
                        'key' => $statusKey,
                        'label' => $docService->formatDocumentationStatusLabel($statusKey),
                        'count' => $count,
                    ];
                }
            }

            $result[$category] = [
                'total' => $total,
                'completed' => $completed,
                'uncompleted' => $total - $completed,
                'data_issues' => $dataIssuesByCategory[$category] ?? [],
                'missing_steps' => $missingSteps,
            ];
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
            'card' => 'card_provider',
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
            $scoped->where('transactions.documentation_category', $category)
                ->orWhere(function (Builder $fallback) use ($category): void {
                    $fallback->whereNull('transactions.documentation_category');
                    self::applyInferredCategoryScope($fallback, $category);
                });
        });
    }

    public static function applyInferredCategoryScope(Builder $query, string $category): Builder
    {
        match ($category) {
            'client_payment' => $query->where('transactions.type', 'Income')->has('invoices'),
            'account_feed' => $query->where('transactions.type', 'Income')->doesntHave('invoices'),
            'refund' => $query->where('transactions.type', 'Income')->doesntHave('invoices'),
            'provider_single' => $query->where('transactions.type', 'Outflow')->has('bills', '=', 1),
            'provider_bulk' => $query->where('transactions.type', 'Outflow')->has('bills', '>=', 2),
            'card_expense' => $query->where('transactions.type', 'Expense')->where(function (Builder $scoped): void {
                self::applyCardPaymentBankTextScope($scoped);
            }),
            'card_provider' => $query->where('transactions.type', 'Outflow')->doesntHave('bills'),
            'patient_refund' => $query->where('transactions.type', 'Outflow')->where('transactions.related_type', 'Patient'),
            'capital_return' => $query->where('transactions.type', 'Outflow')->where('transactions.related_type', 'Other'),
            'expense_payment' => $query->where('transactions.type', 'Expense'),
            'refunded_payment' => $query->whereIn('transactions.type', ['Outflow', 'Expense'])
                ->where('transactions.documentation_category', 'refunded_payment'),
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
        ];
    }

    /**
     * @return array{total: int, statuses: array<int, array{key: string, label: string, count: int}>}
     */
    public function documentationStatusBreakdown(Builder $query): array
    {
        $docService = app(TransactionDocumentationService::class);
        $statusCounts = (clone $query)
            ->selectRaw('documentation_status, COUNT(*) as aggregate')
            ->groupBy('documentation_status')
            ->pluck('aggregate', 'documentation_status');

        $statuses = [];

        foreach (self::documentationStatusKeys() as $statusKey) {
            $statuses[] = [
                'key' => $statusKey,
                'label' => $docService->formatDocumentationStatusLabel($statusKey),
                'count' => (int) ($statusCounts[$statusKey] ?? 0),
            ];
        }

        return [
            'total' => (int) array_sum($statusCounts->all()),
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

    /**
     * Documentation statuses that represent work-in-progress (excluding unlinked).
     *
     * @return array<int, string>
     */
    public static function incompleteDocumentationStatuses(): array
    {
        return ['incomplete', 'missing_attachment', 'missing_generated_pdf'];
    }

    /**
     * @return array{total: int, done: int, unlinked: int, incomplete: int}
     */
    public function countDocumentationSummary(Builder $query): array
    {
        $row = (clone $query)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN documentation_status = 'complete' THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN documentation_status = 'unlinked' THEN 1 ELSE 0 END) as unlinked,
            SUM(CASE WHEN documentation_status IN ('incomplete', 'missing_attachment', 'missing_generated_pdf') THEN 1 ELSE 0 END) as incomplete
        ")->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'done' => (int) ($row->done ?? 0),
            'unlinked' => (int) ($row->unlinked ?? 0),
            'incomplete' => (int) ($row->incomplete ?? 0),
        ];
    }

    /**
     * @return array{all: array{total: int, done: int, unlinked: int, incomplete: int}, income: array{total: int, done: int, unlinked: int, incomplete: int}, outflow: array{total: int, done: int, unlinked: int, incomplete: int}}
     */
    public function simpleSummary(Builder $query): array
    {
        $row = (clone $query)->selectRaw("
            COUNT(*) as all_total,
            SUM(CASE WHEN documentation_status = 'complete' THEN 1 ELSE 0 END) as all_done,
            SUM(CASE WHEN documentation_status = 'unlinked' THEN 1 ELSE 0 END) as all_unlinked,
            SUM(CASE WHEN documentation_status IN ('incomplete', 'missing_attachment', 'missing_generated_pdf') THEN 1 ELSE 0 END) as all_incomplete,
            SUM(CASE WHEN type = 'Income' THEN 1 ELSE 0 END) as income_total,
            SUM(CASE WHEN type = 'Income' AND documentation_status = 'complete' THEN 1 ELSE 0 END) as income_done,
            SUM(CASE WHEN type = 'Income' AND documentation_status = 'unlinked' THEN 1 ELSE 0 END) as income_unlinked,
            SUM(CASE WHEN type = 'Income' AND documentation_status IN ('incomplete', 'missing_attachment', 'missing_generated_pdf') THEN 1 ELSE 0 END) as income_incomplete,
            SUM(CASE WHEN type IN ('Outflow', 'Expense') THEN 1 ELSE 0 END) as outflow_total,
            SUM(CASE WHEN type IN ('Outflow', 'Expense') AND documentation_status = 'complete' THEN 1 ELSE 0 END) as outflow_done,
            SUM(CASE WHEN type IN ('Outflow', 'Expense') AND documentation_status = 'unlinked' THEN 1 ELSE 0 END) as outflow_unlinked,
            SUM(CASE WHEN type IN ('Outflow', 'Expense') AND documentation_status IN ('incomplete', 'missing_attachment', 'missing_generated_pdf') THEN 1 ELSE 0 END) as outflow_incomplete
        ")->first();

        return [
            'all' => [
                'total' => (int) ($row->all_total ?? 0),
                'done' => (int) ($row->all_done ?? 0),
                'unlinked' => (int) ($row->all_unlinked ?? 0),
                'incomplete' => (int) ($row->all_incomplete ?? 0),
            ],
            'income' => [
                'total' => (int) ($row->income_total ?? 0),
                'done' => (int) ($row->income_done ?? 0),
                'unlinked' => (int) ($row->income_unlinked ?? 0),
                'incomplete' => (int) ($row->income_incomplete ?? 0),
            ],
            'outflow' => [
                'total' => (int) ($row->outflow_total ?? 0),
                'done' => (int) ($row->outflow_done ?? 0),
                'unlinked' => (int) ($row->outflow_unlinked ?? 0),
                'incomplete' => (int) ($row->outflow_incomplete ?? 0),
            ],
        ];
    }

    /**
     * @return array{all: array{total: int, done: int, unlinked: int, incomplete: int}, income: array{total: int, done: int, unlinked: int, incomplete: int}, outflow: array{total: int, done: int, unlinked: int, incomplete: int}}
     */
    public function simpleSummaryForBankAccount(int $bankAccountId): array
    {
        return Cache::remember(
            self::summaryCacheKey($bankAccountId),
            300,
            fn (): array => $this->simpleSummary(
                Transaction::query()->where('bank_account_id', $bankAccountId),
            ),
        );
    }

    protected static function summaryCacheKey(int $bankAccountId): string
    {
        return "txn_doc_stats:summary:{$bankAccountId}";
    }

    protected static function applyCardPaymentBankTextScope(Builder $query): void
    {
        $query->where(function (Builder $scoped): void {
            foreach (['notes', 'reference', 'name'] as $column) {
                $scoped->orWhereRaw(
                    "LOWER(COALESCE(transactions.{$column}, '')) LIKE ?",
                    ['%tarjeta%']
                )->orWhereRaw(
                    "LOWER(COALESCE(transactions.{$column}, '')) LIKE ?",
                    ['%tarj%']
                );
            }
        });
    }
}
