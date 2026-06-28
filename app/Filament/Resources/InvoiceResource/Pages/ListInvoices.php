<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function mount(): void
    {
        parent::mount();

        $filters = $this->tableFilters ?? [];

        unset($filters['settlement_issue'], $filters['paid_no_transaction']);

        $this->tableFilters = $filters;
    }
}