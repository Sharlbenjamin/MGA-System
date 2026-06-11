<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\File;
use App\Filament\Widgets\Traits\HasDashboardFilters;

class FileStatsOverview extends  StatsOverviewWidget
{
    use InteractsWithPageFilters, HasDashboardFilters;

    public ?string $filter = 'Month';

    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin');
    }

    protected function getStats(): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();

        // Cases created in the period → all their invoices & bills; expenses by transaction date
        $current = $this->getFileBasedFinancials('current');
        $revenue = $current['revenue'];
        $cost = $current['cost'];
        $expenses = $current['expenses'];
        $income = $current['income'];
        $outflow = $current['outflow'];
        $profit = $current['profit'];

        $previous = $this->getFileBasedFinancials('previous');
        $previousRevenue = $previous['revenue'];
        $previousCost = $previous['cost'];
        $previousExpenses = $previous['expenses'];
        $previousIncome = $previous['income'];
        $previousOutflow = $previous['outflow'];
        $previousProfit = $previous['profit'];

        // Calculate comparisons
        $revenueComparison = $this->calculateComparison($revenue, $previousRevenue);
        $incomeComparison = $this->calculateComparison($income, $previousIncome);
        $profitComparison = $this->calculateComparison($profit, $previousProfit);
        $costComparison = $this->calculateComparison($cost, $previousCost);
        $expensesComparison = $this->calculateComparison($expenses, $previousExpenses);
        $outflowComparison = $this->calculateComparison($outflow, $previousOutflow);

        // Chart data (bucketed by case creation date for revenue/cost, transaction date for expenses)
        $revenueChart = $this->getFileBasedChartData('revenue');
        $costChart = $this->getFileBasedChartData('cost');
        $expensesChart = $this->getExpensesChartData();

        $incomeChart = array_map(function($revenueChart, $costChart) {
            return $revenueChart - $costChart;
        }, $revenueChart, $costChart);
        
        $outflowChart = array_map(function($costChart, $expensesChart) {
            return $costChart + $expensesChart;
        }, $costChart, $expensesChart);
        
        $profitChart = array_map(function($revenueChart, $outflowChart) {
            return $revenueChart - $outflowChart;
        }, $revenueChart, $outflowChart);

        // File statistics
        $activeFiles = File::where('status', 'Assisted')
            ->whereBetween('created_at', [
                $dateRange['current']['start'],
                $dateRange['current']['end']
            ])->count();
            
        $cancelledFiles = File::whereIn('status', ['Cancelled', 'Void'])
            ->whereBetween('created_at', [
                $dateRange['current']['start'],
                $dateRange['current']['end']
            ])->count();
            
        $cancelledCount = File::where('status', 'Cancelled')
            ->whereBetween('created_at', [
                $dateRange['current']['start'],
                $dateRange['current']['end']
            ])->count();
            
        $voidCount = File::where('status', 'Void')
            ->whereBetween('created_at', [
                $dateRange['current']['start'],
                $dateRange['current']['end']
            ])->count();
            
        $totalFiles = File::whereBetween('created_at', [
            $dateRange['current']['start'],
            $dateRange['current']['end']
        ])->count();

        // Previous period file statistics
        $previousActiveFiles = File::where('status', 'Assisted')
            ->whereBetween('created_at', [
                $dateRange['previous']['start'],
                $dateRange['previous']['end']
            ])->count();
            
        $previousCancelledFiles = File::whereIn('status', ['Cancelled', 'Void'])
            ->whereBetween('created_at', [
                $dateRange['previous']['start'],
                $dateRange['previous']['end']
            ])->count();
            
        $previousTotalFiles = File::whereBetween('created_at', [
            $dateRange['previous']['start'],
            $dateRange['previous']['end']
        ])->count();

        // File comparisons
        $activeFilesComparison = $this->calculateComparison($activeFiles, $previousActiveFiles);
        $cancelledFilesComparison = $this->calculateComparison($cancelledFiles, $previousCancelledFiles);
        $totalFilesComparison = $this->calculateComparison($totalFiles, $previousTotalFiles);

        $periodLabel = match ($filters['duration']) {
            'Day' => 'Day',
            'Month' => 'Month',
            default => 'Year',
        };

        return [
            Stat::make("Revenue this {$periodLabel}", '€' . number_format($revenue))
                ->description($this->formatComparisonDescription($revenueComparison))
                ->descriptionIcon($revenueComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($revenueComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($revenueComparison))
                ->chart($revenueChart),

            Stat::make("Income this {$periodLabel}", '€' . number_format($income))
                ->description($this->formatComparisonDescription($incomeComparison))
                ->descriptionIcon($incomeComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($incomeComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($incomeComparison))
                ->chart($incomeChart),

            Stat::make("Profit this {$periodLabel}", '€' . number_format($profit))
                ->description($this->formatComparisonDescription($profitComparison))
                ->descriptionIcon($profitComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($profitComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($profitComparison))
                ->chart($profitChart),

            Stat::make("Cost this {$periodLabel}", '€' . number_format($cost))
                ->description($this->formatComparisonDescription($costComparison))
                ->descriptionIcon($costComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($costComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($costComparison))
                ->chart($costChart),

            Stat::make("Expenses this {$periodLabel}", '€' . number_format($expenses))
                ->description($this->formatComparisonDescription($expensesComparison))
                ->descriptionIcon($expensesComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($expensesComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($expensesComparison))
                ->chart($expensesChart),

            Stat::make("Outflow this {$periodLabel}", '€' . number_format($outflow))
                ->description($this->formatComparisonDescription($outflowComparison))
                ->descriptionIcon($outflowComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($outflowComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($outflowComparison))
                ->chart($outflowChart),

            Stat::make('Active Files', $activeFiles)
                ->description($this->formatComparisonDescription($activeFilesComparison))
                ->descriptionIcon($activeFilesComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($activeFilesComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($activeFilesComparison)),

            Stat::make("Cancelled ({$cancelledCount})", $cancelledFiles)
                ->description($this->formatComparisonDescription($cancelledFilesComparison))
                ->descriptionIcon($cancelledFilesComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($cancelledFilesComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($cancelledFilesComparison)),

            Stat::make('Total Files', $totalFiles)
                ->description($this->formatComparisonDescription($totalFilesComparison))
                ->descriptionIcon($totalFilesComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($totalFilesComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($totalFilesComparison)),
        ];
    }

    protected function hasFiltersForm(): bool
    {
        return true;
    }
}
