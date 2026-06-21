<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InvoicesWithSettlementIssuesResource\Pages\ListInvoicesWithSettlementIssues;
use App\Filament\Resources\TransactionsInWithoutInvoicesResource;
use App\Services\InvoiceSettlementIntegrityService;
use App\Services\TransactionIntegrityService;
use App\Models\Transaction;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class InvoiceSettlementIssuesOverviewWidget extends Widget
{
    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.invoice-settlement-issues-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Settlement overview';

    public ?string $activeIssueType = null;

    #[On('refresh-invoice-settlement-stats')]
    public function refreshSettlementStats(): void
    {
        //
    }

    public function getTotalIssuesProperty(): int
    {
        return InvoiceSettlementIntegrityService::settlementIssueCount();
    }

    /**
     * @return array<string, int>
     */
    public function getIssueCountsProperty(): array
    {
        return InvoiceSettlementIntegrityService::issueTypeCounts();
    }

    public function getTransactionLinkIssuesProperty(): int
    {
        return TransactionIntegrityService::scopeIncomeLinkIssues(Transaction::query())->count();
    }

    public function getActiveFilterLabelProperty(): ?string
    {
        if (blank($this->activeIssueType)) {
            return null;
        }

        return InvoiceSettlementIntegrityService::issueTypeLabel($this->activeIssueType);
    }

    public function applyIssueFilter(string $issueType): void
    {
        $this->dispatch(
            'apply-settlement-issue-filter',
            issueType: $issueType,
        )->to(ListInvoicesWithSettlementIssues::class);
    }

    public function clearIssueFilter(): void
    {
        $this->dispatch('clear-settlement-issue-filter')
            ->to(ListInvoicesWithSettlementIssues::class);
    }

    public function getTransactionsInWithoutInvoicesUrl(): string
    {
        return TransactionsInWithoutInvoicesResource::getUrl('index');
    }
}
