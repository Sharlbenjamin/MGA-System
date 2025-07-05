<?php

namespace App\Filament\Resources\FilesWithoutBillsResource\Pages;

use App\Filament\Resources\FilesWithoutBillsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFilesWithoutBills extends ListRecords
{
    protected static string $resource = FilesWithoutBillsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing files without bills
        ];
    }
} 