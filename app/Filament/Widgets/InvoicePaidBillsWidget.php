<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InvoicePaidBillsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        // Number of bills with paid invoices
        $invoicePaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->count();

        return [
            Stat::make('Invoice Paid Bills', $invoicePaidBills)
                ->description('Bills with paid invoices')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success'),
        ];
    }
} 