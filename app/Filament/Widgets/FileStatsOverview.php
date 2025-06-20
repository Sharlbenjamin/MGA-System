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
use Carbon\Carbon;

class FileStatsOverview extends  StatsOverviewWidget
{
    use InteractsWithPageFilters;

    public ?string $filter = 'Month';

    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin');
    }

    protected function getStats(): array
    {
        $filter = $this->filters['monthYearFilter'] ?? 'Month';

        $Revenue = Invoice::when($filter === 'Month', function($query){
            $query->whereMonth('invoice_date', now()->month);
        })->when($filter === 'Year', function($query){
            $query->whereYear('invoice_date', now()->year);
        })->sum('total_amount');
        $Cost = Bill::when($filter === 'Month', function($query){
            $query->whereMonth('bill_date', now()->month);
        })->when($filter === 'Year', function($query){
            $query->whereYear('bill_date', now()->year);
        })->sum('total_amount') ;
        $Expenses = Transaction::where('type', 'Expense')->when($filter === 'Month', function($query){
            $query->whereMonth('date', now()->month);
        })->when($filter === 'Year', function($query){
            $query->whereYear('date', now()->year);
        })->sum('amount');

        $Income = $Revenue - $Cost ;
        $Outflow = $Cost + $Expenses;
        $Profit = $Revenue - $Outflow ;

        $RevenueChart = Invoice::when($filter === 'Month', function($query){
            $query->selectRaw('DATE(invoice_date) as date, SUM(total_amount) as total')
                  ->whereMonth('invoice_date', now()->month)
                  ->whereYear('invoice_date', now()->year)
                  ->groupBy('date')
                  ->orderBy('date');
        })->when($filter === 'Year', function($query){
            $query->selectRaw('DATE_FORMAT(invoice_date, "%Y-%m") as date, SUM(total_amount) as total')
                  ->whereYear('invoice_date', now()->year)
                  ->groupBy('date')
                  ->orderBy('date');
        })->pluck('total')->toArray();

        $CostChart = Bill::when($filter === 'Month', function($query){
            $query->selectRaw('DATE(bill_date) as date, SUM(total_amount) as total')
                  ->whereMonth('bill_date', now()->month)
                  ->whereYear('bill_date', now()->year)
                  ->groupBy('date')
                  ->orderBy('date');
        })->when($filter === 'Year', function($query){
            $query->selectRaw('DATE_FORMAT(bill_date, "%Y-%m") as date, SUM(total_amount) as total')
                  ->whereYear('bill_date', now()->year)
                  ->groupBy('date')
                  ->orderBy('date');
        })->pluck('total')->toArray();

        $ExpensesChart = Transaction::when($filter === 'Month', function($query){
            $query->selectRaw('DATE(date) as date, SUM(amount) as total')
                  ->whereMonth('date', now()->month)
                  ->whereYear('date', now()->year)
                  ->groupBy('date')
                  ->orderBy('date');
        })->when($filter === 'Year', function($query){
            $query->selectRaw('DATE_FORMAT(date, "%Y-%m") as date, SUM(amount) as total')
                  ->whereYear('date', now()->year)
                  ->groupBy('date')
                  ->orderBy('date');
        })->pluck('total')->toArray();

        $IncomeChart = array_map(function($RevenueChart, $CostChart) {
            return $RevenueChart - $CostChart;
        }, $RevenueChart, $CostChart);
        $OutflowChart = array_map(function($CostChart, $ExpensesChart) {
            return $CostChart + $ExpensesChart;
        }, $CostChart, $ExpensesChart);
        $ProfitChart = array_map(function($RevenueChart, $OutflowChart) {
            return $RevenueChart - $OutflowChart;
        }, $RevenueChart, $OutflowChart);

        $ActiveFiles = $this->queryFilter( File::where('status', 'Assisted'))->count();
        $CancelledFiles = $this->queryFilter( File::where('status', 'Cancelled'))->count();
        $TotalFiles = $this->queryFilter(File::query())->count();

        return [
            Stat::make('Revenue this '.$filter, '€' . number_format($Revenue))
                ->description("Sum of all Invoices  this ".$filter)->chart($RevenueChart)
                ->color('success'),

            Stat::make('Income this '.$filter, '€' . number_format($Income))
                ->description("Revenue after removing the cost")->chart($IncomeChart)
                ->color('success'),

            Stat::make('Profit this '.$filter, '€' . number_format($Profit))
                ->description("Profit after removing all Provider Cost and Expenses")->chart($ProfitChart)
                ->color('success'),

            Stat::make('Cost this '.$filter, '€' . number_format($Cost))
                ->description("Provider Cost this ".$filter)->chart($CostChart)
                ->color('warning'),

            Stat::make('Expenses this '.$filter, '€' . number_format($Expenses))
                ->description("Utilities, Salaries and Lawyer")->chart($ExpensesChart)
                ->color('info'),

            Stat::make('Outflow this '.$filter, '€' . number_format($Outflow))
                ->description("Cost and Expenses")->chart($OutflowChart)
                ->color('danger'),

            Stat::make('Active Files', $ActiveFiles)
                ->description("Active Files this $filter")
                ->color('success'),

            Stat::make('Cancelled', $CancelledFiles)
                ->description("Cancelled Files this $filter")
                ->color('danger'),

            Stat::make('Total Files', $TotalFiles)
                ->description("Total Files this $filter")
                ->color('info'),
        ];
    }
    protected function hasFiltersForm(): bool
    {
        return true;
    }

    public function queryFilter($query)
    {
        $filter = $this->filters['monthYearFilter'] ?? 'Month';
        if($filter == 'Month'){
            return $query->whereMonth('created_at', now()->month);
        }else{
            return $query->whereYear('created_at', now()->year);
        }
    }

    public function groupFilter($query)
    {
        $filter = $this->filters['monthYearFilter'] ?? 'Month';
        if($filter == 'Month'){
            return $query->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d") as period, SUM(total_amount) as total')
                        ->whereMonth('invoice_date', now()->month)
                        ->whereYear('invoice_date', now()->year)
                        ->groupBy('period')
                        ->orderBy('period');
        }else{
            return $query->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, SUM(total_amount) as total')
                        ->whereYear('invoice_date', now()->year)
                        ->groupBy('period')
                        ->orderBy('period');
        }
    }

}
