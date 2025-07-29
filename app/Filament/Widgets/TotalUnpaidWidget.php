<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalUnpaidWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 2;

    protected function getStats(): array
    {
        // Total amount of unpaid bills (all bills, not just those with paid invoices)
        $totalUnpaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->sum(DB::raw('total_amount - paid_amount'));

        return [
            Stat::make('Total Unpaid', '€' . number_format($totalUnpaidAmount, 2))
                ->description('Total amount to be paid (all bills)')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),
        ];
    }
} 