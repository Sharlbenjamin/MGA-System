<?php

namespace App\Filament\Resources\InvoicesWithoutDocsResource\Pages;

use App\Filament\Resources\InvoicesWithoutDocsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoicesWithoutDocs extends ListRecords
{
    protected static string $resource = InvoicesWithoutDocsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 