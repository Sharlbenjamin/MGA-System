<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use App\Models\File;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class FileStatsOverview extends BaseWidget
{
    public static function shouldLoad(): bool
    {
        $user = Auth::user();
        return $user && $user->roles->contains('name', 'admin');
    }

    protected function getStats(): array
    {
        // Get total revenue for current year
        $totalRevenue = Invoice::whereYear('invoice_date', now()->year)
            ->sum('total_amount');

        // Get total expenses for current year
        $totalExpenses = Bill::whereYear('bill_date', now()->year)
            ->sum('total_amount');

        // Calculate total profit
        $totalProfit = $totalRevenue - $totalExpenses;

        $totalFiles = File::where('created_at', '>=', now()->subMonths(1))->count();
        $assistedFiles = File::where('status', 'assisted')->where('created_at', '>=', now()->subMonths(1))->count();
        $cancelledFiles = File::where('status', 'cancelled')->where('created_at', '>=', now()->subMonths(1))->count();

        return [
            Stat::make('Revenue', '€'.number_format($totalRevenue, 0))->description('Revenue this year')->color('success'),
            Stat::make('Expenses', '€'.number_format($totalExpenses, 0))->description('Expenses this year')->color('danger'),
            Stat::make('Profit', '€'.number_format($totalProfit, 0))->description('Profit this year')->color('info'),
            Stat::make('Assisted Files', $assistedFiles)->description('Assisted Files this month')->color('success'),
            Stat::make('Cancelled Files', $cancelledFiles)->description('Cancelled Files this month')->color('danger'),
            Stat::make('Total Files', $totalFiles)->description('Total Files this month')->color('info'),
        ];
    }
}
