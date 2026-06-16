<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

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
