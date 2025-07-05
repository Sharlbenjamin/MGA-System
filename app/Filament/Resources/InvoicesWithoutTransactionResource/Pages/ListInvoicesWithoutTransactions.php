<?php

namespace App\Filament\Resources\InvoicesWithoutTransactionResource\Pages;

use App\Filament\Resources\InvoicesWithoutTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoicesWithoutTransactions extends ListRecords
{
    protected static string $resource = InvoicesWithoutTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing invoices without transactions
        ];
    }
} 
 