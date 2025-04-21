<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use App\Models\File;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FileStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Get revenue data grouped by month for current year
        $revenueLastMonths = Invoice::whereYear('invoice_date', now()->year)
            ->selectRaw('MONTH(invoice_date) as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total')
            ->map(function ($amount) {
                return number_format($amount, 0) ?? 0;
            })
            ->toArray();

        // Ensure revenue has all 12 months with 0 as default value
        $revenueLastMonths = array_replace(
            array_fill(1, 12, 0),
            $revenueLastMonths
        );

        $expensesLastMonths = Bill::whereYear('bill_date', now()->year)
            ->selectRaw('MONTH(bill_date) as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total')
            ->map(function ($amount) {
                return number_format($amount, 0) ?? 0;
            })
            ->toArray();

        // Ensure expenses has all 12 months with 0 as default value
        $expensesLastMonths = array_replace(
            array_fill(1, 12, 0),
            $expensesLastMonths
        );

        // Calculate profit by subtracting expenses from revenue for each month
        $profitLastMonths = array_map(function ($revenue, $expense) {
            return $revenue - $expense;
        }, $revenueLastMonths, $expensesLastMonths);

        $totalFiles = File::where('created_at', '>=', now()->subMonths(1))->count();
        $assistedFiles = File::where('status', 'assisted')->where('created_at', '>=', now()->subMonths(1))->count();
        $cancelledFiles = File::where('status', 'cancelled')->where('created_at', '>=', now()->subMonths(1))->count();

        return [
// Revenue months
            Stat::make('Revenue', '€'.end($revenueLastMonths))->description('Revenue this year')->chart([$revenueLastMonths])->color('success'),
            Stat::make('Expenses', '€'.end($expensesLastMonths))->description('Expenses this year')->chart([$expensesLastMonths])->color('danger'),
            Stat::make('Profit', '€'.end($profitLastMonths))->description('Profit this year')->chart([$profitLastMonths])->color('info'),
// cases per months
            Stat::make('Assisted Files', $assistedFiles)->description('Assisted Files this month')->color('success'),
            Stat::make('Cancelled Files', $cancelledFiles)->description('Cancelled Files this month')->color('danger'),
            Stat::make('Total Files', $totalFiles)->description('Total Files this month')->color('info'),
        ];
    }
}
