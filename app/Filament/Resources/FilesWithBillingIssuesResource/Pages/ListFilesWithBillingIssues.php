<?php

namespace App\Filament\Resources\FilesWithBillingIssuesResource\Pages;

use App\Filament\Resources\FilesWithBillingIssuesResource;
use App\Filament\Widgets\FileBillingIssuesOverviewWidget;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListFilesWithBillingIssues extends ListRecords
{
    protected static string $resource = FilesWithBillingIssuesResource::class;

    public ?string $activeWidgetIssueType = null;

    protected function getHeaderWidgets(): array
    {
        return [
            FileBillingIssuesOverviewWidget::make([
                'activeIssueType' => $this->activeWidgetIssueType,
            ]),
        ];
    }

    #[On('apply-billing-issue-filter')]
    public function applyBillingIssueFilter(string $issueType): void
    {
        $filters = $this->tableFilters ?? [];

        $filters['issue_type'] = ['value' => $issueType];

        $this->activeWidgetIssueType = $issueType;
        $this->tableFilters = $filters;
        $this->resetTable();
    }

    #[On('clear-billing-issue-filter')]
    public function clearBillingIssueFilter(): void
    {
        $filters = $this->tableFilters ?? [];

        unset($filters['issue_type']);

        $this->activeWidgetIssueType = null;
        $this->tableFilters = $filters;
        $this->resetTable();
    }
}
