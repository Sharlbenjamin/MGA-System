<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Services\TransactionDocumentationStatsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

class TransactionDocumentationStatsWidget extends Widget
{
    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.transaction-documentation-breakdown';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Transaction summary';

    public ?int $bankAccountId = null;

    public ?string $activeTypeScope = null;

    public ?string $activeStatus = null;

    #[On('refresh-transaction-documentation-stats')]
    public function refreshDocumentationStats(): void
    {
        if ($this->bankAccountId !== null) {
            TransactionDocumentationStatsService::forgetBankAccountCache($this->bankAccountId);
        }
    }

    public function getSummaryProperty(): ?array
    {
        if (! Schema::hasColumn('transactions', 'documentation_status') || $this->bankAccountId === null) {
            return null;
        }

        return app(TransactionDocumentationStatsService::class)->simpleSummaryForBankAccount(
            $this->bankAccountId,
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
}
