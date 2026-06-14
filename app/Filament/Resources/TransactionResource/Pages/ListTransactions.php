<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Exports\BankStatementTransactionsExport;
use App\Filament\Resources\TransactionResource;
use App\Filament\Widgets\TransactionDocumentationStatsWidget;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListTransactions extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportBankFormat')
                ->label('Export bank format')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $filename = 'bank-transactions-' . now()->format('Y-m-d-His') . '.xlsx';

                    return Excel::download(
                        new BankStatementTransactionsExport($this->getFilteredTableQuery()),
                        $filename
                    );
                }),
            Actions\Action::make('import')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(TransactionResource::getUrl('import')),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionDocumentationStatsWidget::class,
        ];
    }
}
