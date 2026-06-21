<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Services\TransactionDocumentationStatsService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TransactionDocumentationStatsWidget extends Widget
{
    use InteractsWithPageTable;

    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.transaction-documentation-breakdown';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documentation overview';

    public ?int $bankAccountId = null;

    public ?string $activeCategory = null;

    public ?string $activeCompletion = null;

    public ?string $activeDocumentationStatus = null;

    public ?string $activeDataIssue = null;

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }

    protected function getTablePageMountParameters(): array
    {
        if ($this->bankAccountId === null) {
            return [];
        }

        return ['bankAccountId' => $this->bankAccountId];
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

        if ($this->bankAccountId === null) {
            return app(TransactionDocumentationStatsService::class)->breakdown(
                $this->getPageTableQuery()
            );
        }

        return app(TransactionDocumentationStatsService::class)->breakdownForBankAccount(
            $this->bankAccountId,
        );
    }

    public function getStatusOverviewProperty(): ?array
    {
        if (! Schema::hasColumn('transactions', 'documentation_status')) {
            return null;
        }

        if ($this->bankAccountId !== null) {
            return app(TransactionDocumentationStatsService::class)->documentationStatusBreakdownForBankAccount(
                $this->bankAccountId,
            );
        }

        return app(TransactionDocumentationStatsService::class)->documentationStatusBreakdown(
            $this->getPageTableQuery(),
        );
    }

    public function getActiveFilterLabelProperty(): ?string
    {
        $parts = [];

        if (filled($this->activeCategory)) {
            $parts[] = TransactionDocumentationStatsService::categoryLabel($this->activeCategory);
        }

        if (filled($this->activeDocumentationStatus)) {
            $parts[] = app(\App\Services\TransactionDocumentationService::class)
                ->formatDocumentationStatusLabel($this->activeDocumentationStatus);
        } elseif ($this->activeCompletion === 'completed') {
            $parts[] = 'Completed';
        } elseif ($this->activeCompletion === 'uncompleted') {
            $parts[] = 'Uncompleted';
        }

        if (filled($this->activeDataIssue)) {
            $parts[] = match ($this->activeDataIssue) {
                'transaction_invoice_total_mismatch' => 'Amount mismatch',
                'paid_amount_mismatch' => 'Paid invoice mismatch',
                default => str_replace('_', ' ', $this->activeDataIssue),
            };
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    public function applyDocumentationFilter(
        string $category,
        string $completion = 'all',
        ?string $documentationStatus = null,
    ): void {
        $this->dispatch(
            'apply-transaction-documentation-filter',
            category: $category,
            completion: $completion,
            documentationStatus: $documentationStatus,
        )->to($this->getTablePage());
    }

    public function applyDataIntegrityFilter(string $issueKey, ?string $category = null): void
    {
        if ($issueKey === 'paid_no_transaction') {
            $this->redirect(InvoiceResource::getUrl('index', [
                'tableFilters' => [
                    'paid_no_transaction' => ['isActive' => true],
                ],
            ]));

            return;
        }

        $this->dispatch(
            'apply-transaction-data-integrity-filter',
            issueKey: $issueKey,
            category: $category,
        )->to($this->getTablePage());
    }

    public function clearDocumentationFilter(): void
    {
        $this->dispatch('clear-transaction-documentation-filter')
            ->to($this->getTablePage());
    }

    public function applyStatusFilter(string $documentationStatus): void
    {
        $this->dispatch(
            'apply-transaction-status-filter',
            documentationStatus: $documentationStatus,
        )->to($this->getTablePage());
    }
}
