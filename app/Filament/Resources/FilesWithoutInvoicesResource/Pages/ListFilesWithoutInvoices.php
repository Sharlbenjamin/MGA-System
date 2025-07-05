<?php

namespace App\Filament\Resources\FilesWithoutInvoicesResource\Pages;

use App\Filament\Resources\FilesWithoutInvoicesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFilesWithoutInvoices extends ListRecords
{
    protected static string $resource = FilesWithoutInvoicesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing files without invoices
        ];
    }
} 