<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UnpaidBillsSummary extends BaseWidget
{
    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }

    protected function getStats(): array
    {
        // Get all unpaid/partial bills with relationships
        $unpaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.invoices'])
            ->get();

        // Bills with paid invoices (BK received bills)
        $billsWithPaidInvoices = $unpaidBills->filter(function ($bill) {
            return $bill->file && $bill->file->invoices->where('status', 'Paid')->count() > 0;
        });

        // Providers needing payment (with paid invoices)
        $providersNeedingPayment = $billsWithPaidInvoices
            ->pluck('provider_id')
            ->filter()
            ->unique()
            ->count();

        // Total outstanding amount (with paid invoices)
        $totalUnpaidAmount = $billsWithPaidInvoices->sum(function ($bill) {
            return $bill->total_amount - $bill->paid_amount;
        });

        // Number of bills with paid invoices
        $invoicePaidBills = $billsWithPaidInvoices->count();

        // All providers needing payment (all unpaid bills)
        $allProvidersNeedingPayment = $unpaidBills
            ->pluck('provider_id')
            ->filter()
            ->unique()
            ->count();

        // Total outstanding amount (all unpaid bills)
        $allTotalUnpaidAmount = $unpaidBills->sum(function ($bill) {
            return $bill->total_amount - $bill->paid_amount;
        });

        // Number of all unpaid bills
        $allUnpaidBills = $unpaidBills->count();

        // Bills with bank accounts (for transfers)
        $billsWithBankAccounts = $unpaidBills->whereNotNull('bank_account_id');
        $totalTransfers = $billsWithBankAccounts->pluck('bank_account_id')->unique()->count();

        return [
            Stat::make('Providers Need Payment', $providersNeedingPayment)
                ->description('Providers with unpaid bills')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('danger'),

            Stat::make('Total Outstanding', '€' . number_format($totalUnpaidAmount, 2))
                ->description('Total amount to be paid')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),

            Stat::make('Invoice Paid Bills', $invoicePaidBills)
                ->description('Bills with paid invoices')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success'),

            Stat::make('All Providers Need Payment', $allProvidersNeedingPayment)
                ->description('All providers with unpaid bills')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('warning'),

            Stat::make('All Total Outstanding', '€' . number_format($allTotalUnpaidAmount, 2))
                ->description('All total amount to be paid')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('warning'),

            Stat::make('All Unpaid Bills', $allUnpaidBills)
                ->description('All unpaid bills')
                ->descriptionIcon('heroicon-m-document')
                ->color('warning'),
            
            Stat::make('Invoice Paid Amount', '€' . number_format($totalUnpaidAmount, 2))
                ->description('Amount with paid invoices')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('All Unpaid Amount', '€' . number_format($allTotalUnpaidAmount, 2))
                ->description('All unpaid amount')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('warning'),

            Stat::make('Total Transfers', $totalTransfers)
                ->description('Bank account groups with unpaid bills')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),
        ];
    }
} 