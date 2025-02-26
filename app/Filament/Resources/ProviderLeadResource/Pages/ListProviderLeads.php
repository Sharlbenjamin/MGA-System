<?php

namespace App\Filament\Resources\ProviderLeadResource\Pages;

use App\Filament\Resources\ProviderLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderLeads extends ListRecords
{
    protected static string $resource = ProviderLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
