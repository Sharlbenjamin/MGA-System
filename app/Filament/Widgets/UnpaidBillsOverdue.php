<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UnpaidBillsOverdue extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Overdue unpaid bills count
        $overdueUnpaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->where('due_date', '<', now())
            ->count();

        // Total amount of overdue unpaid bills
        $overdueUnpaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->where('due_date', '<', now())
            ->sum(DB::raw('total_amount - paid_amount'));

        // Overdue bills with bank accounts
        $overdueWithBankAccount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->where('due_date', '<', now())
            ->whereNotNull('bank_account_id')
            ->count();

        // Average overdue amount
        $averageOverdueAmount = $overdueUnpaidBills > 0 
            ? $overdueUnpaidAmount / $overdueUnpaidBills 
            : 0;

        return [
            Stat::make('Overdue Unpaid Bills', $overdueUnpaidBills)
                ->description('Bills past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Overdue Amount', '€' . number_format($overdueUnpaidAmount, 2))
                ->description('Total overdue amount')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),

            Stat::make('Overdue with Bank Account', $overdueWithBankAccount)
                ->description('Overdue bills with bank accounts')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('warning'),

            Stat::make('Average Overdue Amount', '€' . number_format($averageOverdueAmount, 2))
                ->description('Average amount per overdue bill')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }
} 