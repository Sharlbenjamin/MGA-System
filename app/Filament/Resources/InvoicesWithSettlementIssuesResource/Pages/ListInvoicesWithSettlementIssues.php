<?php

namespace App\Filament\Resources\InvoicesWithSettlementIssuesResource\Pages;

use App\Filament\Resources\InvoicesWithSettlementIssuesResource;
use App\Filament\Widgets\InvoiceSettlementIssuesOverviewWidget;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListInvoicesWithSettlementIssues extends ListRecords
{
    protected static string $resource = InvoicesWithSettlementIssuesResource::class;

    public ?string $activeWidgetIssueType = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InvoiceSettlementIssuesOverviewWidget::make([
                'activeIssueType' => $this->activeWidgetIssueType,
            ]),
        ];
    }

    #[On('apply-settlement-issue-filter')]
    public function applySettlementIssueFilter(string $issueType): void
    {
        $filters = $this->tableFilters ?? [];

        $filters['issue_type'] = ['value' => $issueType];

        $this->activeWidgetIssueType = $issueType;
        $this->tableFilters = $filters;
        $this->resetTable();
    }

    #[On('clear-settlement-issue-filter')]
    public function clearSettlementIssueFilter(): void
    {
        $filters = $this->tableFilters ?? [];

        unset($filters['issue_type']);

        $this->activeWidgetIssueType = null;
        $this->tableFilters = $filters;
        $this->resetTable();
    }
}
