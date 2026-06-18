<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Services\TransactionDocumentationStatsService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TransactionDocumentationStatsWidget extends Widget
{
    use InteractsWithPageTable;

    protected static string $view = 'filament.widgets.transaction-documentation-breakdown';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documentation overview';

    public ?int $bankAccountId = null;

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }

    protected function getTablePageMountParameters(): array
    {
        if ($this->bankAccountId === null) {
            return [];
        }

        return ['bankAccount' => $this->bankAccountId];
    }

    protected function getPageTableQuery(): Builder
    {
        unset($this->tablePage);

        return $this->getTablePageInstance()->getFilteredSortedTableQuery();
    }

    public function getBreakdownProperty(): ?array
    {
        if (! Schema::hasColumn('transactions', 'documentation_status')) {
            return null;
        }

        return app(TransactionDocumentationStatsService::class)->breakdown(
            $this->getPageTableQuery()
        );
    }

    public function applyDocumentationFilter(string $workflow, string $completion = 'all'): void
    {
        $this->dispatch(
            'apply-transaction-documentation-filter',
            workflow: $workflow,
            completion: $completion,
        )->to($this->getTablePage());
    }
}
