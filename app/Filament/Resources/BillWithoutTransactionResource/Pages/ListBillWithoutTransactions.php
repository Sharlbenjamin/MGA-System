<?php

namespace App\Filament\Resources\BillWithoutTransactionResource\Pages;

use App\Filament\Resources\BillWithoutTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBillWithoutTransactions extends ListRecords
{
    protected static string $resource = BillWithoutTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Bills Without Transactions';
    }
} 