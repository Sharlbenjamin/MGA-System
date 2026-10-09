<?php

namespace App\Filament\Resources\BankAccountResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('allTransactions')
                ->label('All transactions')
                ->icon('heroicon-o-banknotes')
                ->url(fn (): string => TransactionResource::getUrl('all')),
            Actions\CreateAction::make(),
        ];
    }
}