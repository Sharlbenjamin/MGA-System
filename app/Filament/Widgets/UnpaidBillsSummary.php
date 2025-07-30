<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UnpaidBillsSummary extends BaseWidget
{

    protected function getStats(): array
    {
        // Get providers that have unpaid bills with paid invoices
        $providersNeedingPayment = Bill::whereIn('bills.status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereNotNull('provider_branch_id')
                    ->whereHas('invoices', function ($invoiceQuery) {
                        $invoiceQuery->where('status', 'Paid');
                    });
            })
            ->join('files', 'bills.file_id', '=', 'files.id')
            ->join('provider_branches', 'files.provider_branch_id', '=', 'provider_branches.id')
            ->distinct('provider_branches.provider_id')
            ->count('provider_branches.provider_id');

        // Total amount of unpaid bills with paid invoices
        $totalUnpaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->sum(DB::raw('total_amount - paid_amount'));

        // Number of bills with paid invoices
        $invoicePaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->count();

        // Total amount of bills with paid invoices
        $invoicePaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->sum(DB::raw('total_amount - paid_amount'));

        // Get providers that have unpaid bills (without invoice filter)
        $allProvidersNeedingPayment = Bill::whereIn('bills.status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereNotNull('provider_branch_id');
            })
            ->join('files', 'bills.file_id', '=', 'files.id')
            ->join('provider_branches', 'files.provider_branch_id', '=', 'provider_branches.id')
            ->distinct('provider_branches.provider_id')
            ->count('provider_branches.provider_id');

        // Total amount of all unpaid bills (without invoice filter)
        $allTotalUnpaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->sum(DB::raw('total_amount - paid_amount'));

        // Number of all unpaid bills (without invoice filter)
        $allUnpaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->count();

        // Total amount of all unpaid bills (without invoice filter)
        $allUnpaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->sum(DB::raw('total_amount - paid_amount'));

        // Fetch unpaid bills, group by bank account, and count the groups
        $totalTransfers = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNotNull('bank_account_id')
            ->groupBy('bank_account_id')
            ->count();

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

            Stat::make('Invoice Paid Amount', '€' . number_format($invoicePaidAmount, 2))
                ->description('Amount with paid invoices')
                ->descriptionIcon('heroicon-m-currency-euro')
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

            Stat::make('All Unpaid Amount', '€' . number_format($allUnpaidAmount, 2))
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