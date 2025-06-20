<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MonthlyProfit extends ChartWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Monthly Profit';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $months = [];
        $invoices = [];
        $bills = [];
        $currentMonth = now()->subMonths(11)->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $months[] = $currentMonth->format('M Y');

            // Get total invoices for this month
            $monthInvoices = DB::table('invoices')
                ->whereYear('invoice_date', $currentMonth->year)
                ->whereMonth('invoice_date', $currentMonth->month)
                ->sum('total_amount');
            $invoices[] = $monthInvoices;

            // Get total bills for this month
            $monthBills = DB::table('bills')
                ->whereYear('bill_date', $currentMonth->year)
                ->whereMonth('bill_date', $currentMonth->month)
                ->sum('total_amount');
            $bills[] = $monthBills;

            $currentMonth->addMonth();
        }

        // Calculate profits
        $profits = array_map(function($invoice, $bill) {
            return $invoice - $bill;
        }, $invoices, $bills);

        // Create organized array with all data
        $monthlyData = [];
        foreach ($months as $index => $month) {
            $monthlyData[] = [
                'month' => $month,
                'invoices' => $invoices[$index],
                'bills' => $bills[$index],
                'profit' => $profits[$index]
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Monthly Profit',
                    'data' => $profits,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $months,
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
