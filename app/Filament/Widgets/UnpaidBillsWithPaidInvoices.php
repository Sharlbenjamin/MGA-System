<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UnpaidBillsWithPaidInvoices extends BaseWidget
{
    protected function getStats(): array
    {
        // Bills with paid invoices count
        $billsWithPaidInvoices = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->count();

        // Amount of bills with paid invoices
        $paidInvoicesAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->sum(DB::raw('total_amount - paid_amount'));

        // Bills with paid invoices and bank accounts
        $paidInvoicesWithBankAccount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNotNull('bank_account_id')
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->count();

        // Average amount for bills with paid invoices
        $averagePaidInvoicesAmount = $billsWithPaidInvoices > 0 
            ? $paidInvoicesAmount / $billsWithPaidInvoices 
            : 0;

        return [
            Stat::make('Bills with Paid Invoices', $billsWithPaidInvoices)
                ->description('Bills that have paid invoices')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success'),

            Stat::make('Paid Invoices Amount', '€' . number_format($paidInvoicesAmount, 2))
                ->description('Total amount for bills with paid invoices')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('Paid Invoices with Bank Account', $paidInvoicesWithBankAccount)
                ->description('Bills with paid invoices and bank accounts')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),

            Stat::make('Average Paid Invoices Amount', '€' . number_format($averagePaidInvoicesAmount, 2))
                ->description('Average amount per bill with paid invoices')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }
} 