<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBills extends ListRecords
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('bills-without-transactions')
                ->label('Bills Without Trx')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url(static::getResource()::getUrl('paid-without-transactions'))
                ->badge(fn () => static::getResource()::getModel()::where('status', 'Paid')->whereDoesntHave('transactions')->count())
                ->badgeColor('warning'),
        ];
    }
}
