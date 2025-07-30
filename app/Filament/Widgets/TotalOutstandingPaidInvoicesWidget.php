<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalOutstandingPaidInvoicesWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        // Total amount of unpaid bills with paid invoices
        $totalUnpaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->sum(DB::raw('total_amount - paid_amount'));

        return [
            Stat::make('Total Outstanding (Paid Invoices)', '€' . number_format($totalUnpaidAmount, 2))
                ->description('Total amount to be paid (files with paid invoices only)')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('danger'),
        ];
    }
} 