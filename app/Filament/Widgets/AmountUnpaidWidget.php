<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AmountUnpaidWidget extends StatsOverviewWidget
{

    protected function getStats(): array
    {
        // Amount unpaid (all bills, not just those with paid invoices)
        $amountUnpaid = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->sum(DB::raw('total_amount - paid_amount'));

        return [
            Stat::make('Amount Unpaid', '€' . number_format($amountUnpaid, 2))
                ->description('Amount unpaid (all bills)')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),
        ];
    }
} 