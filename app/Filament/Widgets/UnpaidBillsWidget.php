<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UnpaidBillsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 2;

    protected function getStats(): array
    {
        // Number of unpaid bills (all bills, not just those with paid invoices)
        $unpaidBillsCount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->count();

        return [
            Stat::make('Unpaid Bills', $unpaidBillsCount)
                ->description('Total unpaid bills (all bills)')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
        ];
    }
} 