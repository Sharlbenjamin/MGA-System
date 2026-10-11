<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ShouldBePaidResource;
use App\Filament\Resources\ShouldBePaidResource\Pages\ListShouldBePaid;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UnpaidBillsSummary extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public string $activePayableState = ShouldBePaidResource::STATE_PAYABLES;

    protected function getColumns(): int
    {
        return 4;
    }

    public function showPayableState(string $state): void
    {
        $this->dispatch('apply-payable-state', state: $state)
            ->to(ListShouldBePaid::class);
    }

    protected function getStats(): array
    {
        $colors = [
            ShouldBePaidResource::STATE_ALL_UNPAID => 'warning',
            ShouldBePaidResource::STATE_PAYABLES => 'danger',
            ShouldBePaidResource::STATE_BK_RECEIVED => 'success',
            ShouldBePaidResource::STATE_OVERDUE => 'danger',
            ShouldBePaidResource::STATE_MISSING_DOCUMENT => 'warning',
            ShouldBePaidResource::STATE_HAS_BANK_ACCOUNT => 'info',
        ];

        $icons = [
            ShouldBePaidResource::STATE_ALL_UNPAID => 'heroicon-m-document',
            ShouldBePaidResource::STATE_PAYABLES => 'heroicon-m-banknotes',
            ShouldBePaidResource::STATE_BK_RECEIVED => 'heroicon-m-document-check',
            ShouldBePaidResource::STATE_OVERDUE => 'heroicon-m-exclamation-triangle',
            ShouldBePaidResource::STATE_MISSING_DOCUMENT => 'heroicon-m-document-minus',
            ShouldBePaidResource::STATE_HAS_BANK_ACCOUNT => 'heroicon-m-building-library',
        ];

        $stats = [];

        foreach (ShouldBePaidResource::payableStates() as $state => $label) {
            $isActive = $this->activePayableState === $state;
            $stats[] = Stat::make($label, ShouldBePaidResource::payableStateQuery($state)->count())
                ->description($isActive ? 'Showing this list' : 'Show these bills')
                ->descriptionIcon($icons[$state])
                ->color($isActive ? 'primary' : $colors[$state])
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "showPayableState('{$state}')",
                ]);
        }

        $activeLabel = ShouldBePaidResource::payableStates()[$this->activePayableState] ?? 'Payables';
        $outstanding = ShouldBePaidResource::outstandingAmountForState($this->activePayableState);

        $stats[] = Stat::make('Outstanding', '€'.number_format($outstanding, 2))
            ->description('Total still owed for '.$activeLabel)
            ->descriptionIcon('heroicon-m-currency-euro')
            ->color('gray');

        return $stats;
    }
}
