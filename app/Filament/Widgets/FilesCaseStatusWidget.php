<?php

namespace App\Filament\Widgets;

use App\Models\File;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class FilesCaseStatusWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Base query: files without invoices (same as FilesWithoutInvoicesResource)
        $baseQuery = fn () => File::query()
            ->whereDoesntHave('invoices');

        // New cases count
        $newCasesCount = (clone $baseQuery())
            ->where('status', 'New')
            ->count();

        // Hold cases count
        $holdCasesCount = (clone $baseQuery())
            ->where('status', 'Hold')
            ->count();

        // Handling & Refund cases with comment needs action
        $handlingRefundWithActionCount = (clone $baseQuery())
            ->whereIn('status', ['Handling', 'Refund'])
            ->whereHas('comments', function (Builder $query) {
                $query->whereRaw('LOWER(content) LIKE ?', ['%needs action%']);
            })
            ->count();

        // Available cases count
        $availableCasesCount = (clone $baseQuery())
            ->where('status', 'Available')
            ->count();

        return [
            Stat::make('New Cases', $newCasesCount)
                ->description('New cases without invoices')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color('success'),

            Stat::make('Hold Cases', $holdCasesCount)
                ->description('Cases on hold')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('warning'),

            Stat::make('Handling', $handlingRefundWithActionCount)
                ->description('Handling & refund cases with comment needs action')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Available Cases', $availableCasesCount)
                ->description('Available cases')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
        ];
    }
}
