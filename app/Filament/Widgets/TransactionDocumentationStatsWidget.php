<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\Transaction;
use App\Services\TransactionDocumentationStatsService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Throwable;

class TransactionDocumentationStatsWidget extends Widget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected static string $view = 'filament.widgets.transaction-documentation-breakdown';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Transaction summary';

    #[Reactive]
    public ?int $bankAccountId = null;

    #[Reactive]
    public ?string $activeTypeScope = null;

    #[Reactive]
    public ?string $activeStatus = null;

    #[On('refresh-transaction-documentation-stats')]
    public function refreshDocumentationStats(): void
    {
        if ($this->bankAccountId !== null) {
            TransactionDocumentationStatsService::forgetBankAccountCache($this->bankAccountId);
        }
    }

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTablePageMountParameters(): array
    {
        return [
            'bankAccountId' => $this->bankAccountId,
        ];
    }

    public function getSummaryProperty(): ?array
    {
        if (! Schema::hasColumn('transactions', 'documentation_status') || $this->bankAccountId === null) {
            return null;
        }

        return app(TransactionDocumentationStatsService::class)->simpleSummary(
            $this->getStatsQuery(),
        );
    }

    public function getActiveFilterLabelProperty(): ?string
    {
        if (blank($this->activeTypeScope) && blank($this->activeStatus)) {
            return null;
        }

        $parts = [];

        if (filled($this->activeTypeScope)) {
            $parts[] = match ($this->activeTypeScope) {
                'income' => 'Trx In',
                'outflow' => 'Trx Out',
                default => 'All transactions',
            };
        }

        if (filled($this->activeStatus)) {
            $parts[] = match ($this->activeStatus) {
                'done' => 'Done',
                'unlinked' => 'Not linked',
                'incomplete' => 'Incomplete',
                default => $this->activeStatus,
            };
        }

        return implode(' · ', $parts);
    }

    public function getTableScopeLabelProperty(): ?string
    {
        $parts = [];

        $month = $this->tableFilters['month']['value'] ?? null;
        if (filled($month)) {
            try {
                $parts[] = Carbon::createFromFormat('Y-m', (string) $month)->format('F Y');
            } catch (Throwable) {
                $parts[] = (string) $month;
            }
        }

        $dateFrom = $this->tableFilters['transaction_date']['date_from'] ?? null;
        $dateUntil = $this->tableFilters['transaction_date']['date_until'] ?? null;

        if (filled($dateFrom) || filled($dateUntil)) {
            $fromLabel = filled($dateFrom) ? Carbon::parse($dateFrom)->format('d/m/Y') : '…';
            $untilLabel = filled($dateUntil) ? Carbon::parse($dateUntil)->format('d/m/Y') : '…';
            $parts[] = $fromLabel.' – '.$untilLabel;
        }

        if (filled($this->tableSearch)) {
            $parts[] = 'Search: '.$this->tableSearch;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', ',').'€';
    }

    public function applyStatFilter(string $typeScope = 'all', ?string $status = null): void
    {
        $this->dispatch(
            'apply-transaction-stat-filter',
            typeScope: $typeScope,
            status: $status,
        )->to(ListTransactions::class);
    }

    public function clearStatFilter(): void
    {
        $this->dispatch('clear-transaction-stat-filter')->to(ListTransactions::class);
    }

    protected function getStatsQuery(): Builder
    {
        try {
            $page = $this->getTablePageInstance();

            if ($page instanceof ListTransactions) {
                return $page->getTableQueryForDocumentationStats();
            }

            return $page->getFilteredTableQuery();
        } catch (Throwable) {
            return Transaction::query()->where('bank_account_id', $this->bankAccountId);
        }
    }
}
