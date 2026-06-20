<?php

namespace App\Filament\Resources\InvoiceChecklistResource\Pages;

use App\Filament\Resources\InvoiceChecklistResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceChecklist extends ListRecords
{
    protected static string $resource = InvoiceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
