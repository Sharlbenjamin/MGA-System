<?php

namespace App\Filament\Resources\TransactionsWithoutDocumentsResource\Pages;

use App\Filament\Resources\TransactionsWithoutDocumentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactionsWithoutDocuments extends ListRecords
{
    protected static string $resource = TransactionsWithoutDocumentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing transactions without documents
        ];
    }
} 