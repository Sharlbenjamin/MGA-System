<?php

namespace App\Filament\Resources\TransactionsInWithoutInvoicesResource\Pages;

use App\Filament\Resources\TransactionsInWithoutInvoicesResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactionsInWithoutInvoices extends ListRecords
{
    protected static string $resource = TransactionsInWithoutInvoicesResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
