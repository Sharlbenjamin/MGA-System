<?php

namespace App\Filament\Resources\ClientOfferChecklistResource\Pages;

use App\Filament\Resources\ClientOfferChecklistResource;
use Filament\Resources\Pages\ListRecords;

class ListClientOfferChecklist extends ListRecords
{
    protected static string $resource = ClientOfferChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
