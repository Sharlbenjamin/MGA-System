<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Transaction;
use App\Filament\Widgets\Traits\HasDashboardFilters;
use Carbon\Carbon;

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
        
        // Current period calculations
        $revenue = Invoice::whereBetween('invoice_date', [
            $dateRange['current']['start'],
            $dateRange['current']['end']
        ])->sum('total_amount');
        
        $cost = Bill::whereBetween('bill_date', [
            $dateRange['current']['start'],
            $dateRange['current']['end']
        ])->sum('total_amount');
        
        $expenses = Transaction::where('type', 'Expense')
            ->whereBetween('date', [
                $dateRange['current']['start'],
                $dateRange['current']['end']
            ])->sum('amount');

        $income = $revenue - $cost;
        $outflow = $cost + $expenses;
        $profit = $revenue - $outflow;

        // Previous period calculations for comparison
        $previousRevenue = Invoice::whereBetween('invoice_date', [
            $dateRange['previous']['start'],
            $dateRange['previous']['end']
        ])->sum('total_amount');
        
        $previousCost = Bill::whereBetween('bill_date', [
            $dateRange['previous']['start'],
            $dateRange['previous']['end']
        ])->sum('total_amount');
        
        $previousExpenses = Transaction::where('type', 'Expense')
            ->whereBetween('date', [
                $dateRange['previous']['start'],
                $dateRange['previous']['end']
            ])->sum('amount');

        $previousIncome = $previousRevenue - $previousCost;
        $previousOutflow = $previousCost + $previousExpenses;
        $previousProfit = $previousRevenue - $previousOutflow;

        // Calculate comparisons
        $revenueComparison = $this->calculateComparison($revenue, $previousRevenue);
        $incomeComparison = $this->calculateComparison($income, $previousIncome);
        $profitComparison = $this->calculateComparison($profit, $previousProfit);
        $costComparison = $this->calculateComparison($cost, $previousCost);
        $expensesComparison = $this->calculateComparison($expenses, $previousExpenses);
        $outflowComparison = $this->calculateComparison($outflow, $previousOutflow);

        // Chart data
        $revenueChart = $this->getChartData('invoices', 'invoice_date', 'total_amount');
        $costChart = $this->getChartData('bills', 'bill_date', 'total_amount');
        $expensesChart = $this->getChartData('transactions', 'date', 'amount', ['type' => 'Expense']);

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

        $periodLabel = $filters['duration'] === 'Month' ? 'Month' : 'Year';

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

            Stat::make('Cancelled', $cancelledFiles)
                ->description($this->formatComparisonDescription($cancelledFilesComparison))
                ->descriptionIcon($cancelledFilesComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($cancelledFilesComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($cancelledFilesComparison)),

            Stat::make('Total Files', $totalFiles)
                ->description($this->formatComparisonDescription($totalFilesComparison))
                ->descriptionIcon($totalFilesComparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($totalFilesComparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($this->getComparisonColor($totalFilesComparison)),
        ];
    }

    protected function getChartData($table, $dateField, $amountField, $additionalConditions = []): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();
        
        $query = \DB::table($table)
            ->whereBetween($dateField, [
                $dateRange['current']['start'],
                $dateRange['current']['end']
            ]);
            
        foreach ($additionalConditions as $field => $value) {
            $query->where($field, $value);
        }
        
        if ($filters['duration'] === 'Day') {
            $data = $query->selectRaw('HOUR(' . $dateField . ') as hour, SUM(' . $amountField . ') as total')
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('total')
                ->toArray();
        } elseif ($filters['duration'] === 'Month') {
            $data = $query->selectRaw('DATE(' . $dateField . ') as date, SUM(' . $amountField . ') as total')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total')
                ->toArray();
        } else {
            $data = $query->selectRaw('DATE_FORMAT(' . $dateField . ', "%Y-%m") as date, SUM(' . $amountField . ') as total')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total')
                ->toArray();
        }
        
        return $data;
    }

    protected function hasFiltersForm(): bool
    {
        return true;
    }
}
