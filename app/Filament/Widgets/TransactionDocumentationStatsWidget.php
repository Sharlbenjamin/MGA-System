<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransactionDocumentationStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'documentation_status')) {
            return [];
        }

        $incomplete = Transaction::query()->where('documentation_status', '!=', 'complete')->count();
        $missingAttachment = Transaction::query()->whereIn('documentation_status', [
            'missing_attachment', 'incomplete',
        ])->count();
        $missingPdf = Transaction::query()->where('documentation_status', 'missing_generated_pdf')->count();
        $missingLinks = Transaction::query()->where('documentation_status', 'missing_linked_record')->count();

        return [
            Stat::make('Incomplete documentation', $incomplete)
                ->description('Transactions not ready for tax lawyer')
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
