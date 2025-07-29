<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UnpaidBillsByBankAccount extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Get unique bank accounts with unpaid bills
        $bankAccountsWithUnpaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNotNull('bank_account_id')
            ->distinct('bank_account_id')
            ->count('bank_account_id');

        // Total amount of unpaid bills by bank account
        $totalUnpaidByBankAccount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNotNull('bank_account_id')
            ->sum(DB::raw('total_amount - paid_amount'));

        // Average amount per bank account
        $averagePerBankAccount = $bankAccountsWithUnpaidBills > 0 
            ? $totalUnpaidByBankAccount / $bankAccountsWithUnpaidBills 
            : 0;

        // Number of bills with bank accounts
        $billsWithBankAccount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNotNull('bank_account_id')
            ->count();

        return [
            Stat::make('Bank Accounts with Unpaid Bills', $bankAccountsWithUnpaidBills)
                ->description('Unique bank accounts')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('warning'),

            Stat::make('Total Outstanding by Bank', '€' . number_format($totalUnpaidByBankAccount, 2))
                ->description('Total amount across all bank accounts')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),

            Stat::make('Average per Bank Account', '€' . number_format($averagePerBankAccount, 2))
                ->description('Average outstanding per bank account')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('Bills with Bank Accounts', $billsWithBankAccount)
                ->description('Total unpaid bills with bank accounts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
        ];
    }
} 