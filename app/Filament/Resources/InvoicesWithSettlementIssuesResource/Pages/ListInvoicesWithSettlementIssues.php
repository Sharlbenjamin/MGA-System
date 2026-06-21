<?php

namespace App\Filament\Resources\InvoicesWithSettlementIssuesResource\Pages;

use App\Filament\Resources\InvoicesWithSettlementIssuesResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoicesWithSettlementIssues extends ListRecords
{
    protected static string $resource = InvoicesWithSettlementIssuesResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
