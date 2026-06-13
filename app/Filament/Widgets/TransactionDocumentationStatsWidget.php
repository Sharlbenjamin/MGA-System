<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TransactionDocumentationStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }

    protected function getPageTableQuery(): Builder
    {
        unset($this->tablePage);

        return $this->getTablePageInstance()->getFilteredSortedTableQuery();
    }

    protected function getCachedStats(): array
    {
        return $this->getStats();
    }

    protected function getStats(): array
    {
        if (! Schema::hasColumn('transactions', 'documentation_status')) {
            return [];
        }

        $query = $this->getPageTableQuery();

        $incomplete = (clone $query)->where('documentation_status', '!=', 'complete')->count();
        $missingAttachment = (clone $query)->whereIn('documentation_status', [
            'missing_attachment',
            'incomplete',
        ])->count();
        $missingPdf = (clone $query)->where('documentation_status', 'missing_generated_pdf')->count();
        $missingLinks = (clone $query)->where('documentation_status', 'missing_linked_record')->count();
        $total = (clone $query)->count();

        return [
            Stat::make('Filtered transactions', $total)
                ->description('Matching current table filters')
                ->color('primary'),
            Stat::make('Incomplete documentation', $incomplete)
                ->description('Not ready for tax lawyer')
                ->color('warning'),
            Stat::make('Missing attachments', $missingAttachment)
                ->color('danger'),
            Stat::make('Missing generated PDFs', $missingPdf)
                ->color('danger'),
            Stat::make('Missing linked records', $missingLinks)
                ->color('gray'),
        ];
    }
}
