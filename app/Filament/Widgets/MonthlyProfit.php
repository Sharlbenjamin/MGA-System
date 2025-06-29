<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Filament\Widgets\Traits\HasDashboardFilters;
use Carbon\Carbon;

class MonthlyProfit extends ChartWidget
{
    use HasDashboardFilters;

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
                    ->whereHour('invoice_date', $hour)
                    ->sum('total_amount');
                
                // Get total bills for this hour
                $hourBills = DB::table('bills')
                    ->whereDate('bill_date', $dateRange['current']['start'])
                    ->whereHour('bill_date', $hour)
                    ->sum('total_amount');
                
                $profits[] = $hourInvoices - $hourBills;
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
                
                $profits[] = $dayInvoices - $dayBills;
                
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
                
                $profits[] = $monthInvoices - $monthBills;
                
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
