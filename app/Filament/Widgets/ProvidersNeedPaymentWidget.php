<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProvidersNeedPaymentWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

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

        return [
            Stat::make('Providers Need Payment', $providersNeedingPayment)
                ->description('Providers with unpaid invoices')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('danger'),
        ];
    }
} 