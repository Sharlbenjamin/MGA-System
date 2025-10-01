<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Filament\Widgets\Traits\HasDashboardFilters;
use Carbon\Carbon;

class MonthlyProfit extends ChartWidget
{
    use HasDashboardFilters;

    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }

    protected static ?int $sort = 3;
    protected static ?string $heading = 'Profit Trend';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $filters = $this->getDashboardFilters();
        $dateRange = $this->getDateRange();
        
        if ($filters['duration'] === 'Day') {
            // For daily view, show hourly data for the selected day
            $labels = [];
            $profits = [];
            
            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = sprintf('%02d:00', $hour);
                
                // Get total invoices for this hour
                $hourInvoices = DB::table('invoices')
                    ->whereDate('invoice_date', $dateRange['current']['start'])
                    ->whereRaw('HOUR(invoice_date) = ?', [$hour])
                    ->sum('total_amount');
                
                // Get total bills for this hour
                $hourBills = DB::table('bills')
                    ->whereDate('bill_date', $dateRange['current']['start'])
                    ->whereRaw('HOUR(bill_date) = ?', [$hour])
                    ->sum('total_amount');
                
                // Get total expenses for this hour
                $hourExpenses = DB::table('transactions')
                    ->where('type', 'Expense')
                    ->whereDate('date', $dateRange['current']['start'])
                    ->whereRaw('HOUR(date) = ?', [$hour])
                    ->sum('amount');
                
                $profits[] = $hourInvoices - ($hourBills + $hourExpenses);
            }
        } elseif ($filters['duration'] === 'Month') {
            // For monthly view, show daily data for the selected month
            $labels = [];
            $profits = [];
            
            $currentDate = $dateRange['current']['start']->copy();
            $endDate = $dateRange['current']['end'];
            
            while ($currentDate <= $endDate) {
                $labels[] = $currentDate->format('M d');
                
                // Get total invoices for this day
                $dayInvoices = DB::table('invoices')
                    ->whereDate('invoice_date', $currentDate)
                    ->sum('total_amount');
                
                // Get total bills for this day
                $dayBills = DB::table('bills')
                    ->whereDate('bill_date', $currentDate)
                    ->sum('total_amount');
                
                // Get total expenses for this day
                $dayExpenses = DB::table('transactions')
                    ->where('type', 'Expense')
                    ->whereDate('date', $currentDate)
                    ->sum('amount');
                
                $profits[] = $dayInvoices - ($dayBills + $dayExpenses);
                
                $currentDate->addDay();
            }
        } else {
            // For yearly view, show monthly data for the selected year
            $labels = [];
            $profits = [];
            
            $currentMonth = $dateRange['current']['start']->copy();
            $endMonth = $dateRange['current']['end'];
            
            while ($currentMonth <= $endMonth) {
                $labels[] = $currentMonth->format('M Y');
                
                // Get total invoices for this month
                $monthInvoices = DB::table('invoices')
                    ->whereYear('invoice_date', $currentMonth->year)
                    ->whereMonth('invoice_date', $currentMonth->month)
                    ->sum('total_amount');
                
                // Get total bills for this month
                $monthBills = DB::table('bills')
                    ->whereYear('bill_date', $currentMonth->year)
                    ->whereMonth('bill_date', $currentMonth->month)
                    ->sum('total_amount');
                
                // Get total expenses for this month
                $monthExpenses = DB::table('transactions')
                    ->where('type', 'Expense')
                    ->whereYear('date', $currentMonth->year)
                    ->whereMonth('date', $currentMonth->month)
                    ->sum('amount');
                
                $profits[] = $monthInvoices - ($monthBills + $monthExpenses);
                
                $currentMonth->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Profit',
                    'data' => $profits,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
