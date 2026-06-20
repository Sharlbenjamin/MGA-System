<?php

namespace App\Filament\Resources\TransactionsOutWithoutBillsResource\Pages;

use App\Filament\Resources\TransactionsOutWithoutBillsResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactionsOutWithoutBills extends ListRecords
{
    protected static string $resource = TransactionsOutWithoutBillsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
