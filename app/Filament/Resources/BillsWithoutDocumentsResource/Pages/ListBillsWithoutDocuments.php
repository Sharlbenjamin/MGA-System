<?php

namespace App\Filament\Resources\BillsWithoutDocumentsResource\Pages;

use App\Filament\Resources\BillsWithoutDocumentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBillsWithoutDocuments extends ListRecords
{
    protected static string $resource = BillsWithoutDocumentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing bills without documents
        ];
    }
} 