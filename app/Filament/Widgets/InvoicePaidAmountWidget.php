<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InvoicePaidAmountWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        // Total amount of bills with paid invoices
        $invoicePaidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->sum(DB::raw('total_amount - paid_amount'));

        return [
            Stat::make('Invoice Paid Amount', '€' . number_format($invoicePaidAmount, 2))
                ->description('Amount with paid invoices')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),
        ];
    }
} 