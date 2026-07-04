<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FilesWithBillingIssuesResource\Pages\ListFilesWithBillingIssues;
use App\Services\FileBillingIntegrityService;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class FileBillingIssuesOverviewWidget extends Widget
{
    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.file-billing-issues-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Billing mismatch overview';

    public ?string $activeIssueType = null;

    #[On('refresh-billing-issue-stats')]
    public function refreshBillingIssueStats(): void
    {
        //
    }

    public function getTotalIssuesProperty(): int
    {
        return FileBillingIntegrityService::billingIssueCount();
    }

    /**
     * @return array<string, int>
     */
    public function getIssueCountsProperty(): array
    {
        return FileBillingIntegrityService::issueTypeCounts();
    }

    public function getActiveFilterLabelProperty(): ?string
    {
        if (blank($this->activeIssueType)) {
            return null;
        }

        return FileBillingIntegrityService::issueTypeLabel($this->activeIssueType);
    }

    public function applyIssueFilter(string $issueType): void
    {
        $this->dispatch(
            'apply-billing-issue-filter',
            issueType: $issueType,
        )->to(ListFilesWithBillingIssues::class);
    }

    public function clearIssueFilter(): void
    {
        $this->dispatch('clear-billing-issue-filter')
            ->to(ListFilesWithBillingIssues::class);
    }
}
