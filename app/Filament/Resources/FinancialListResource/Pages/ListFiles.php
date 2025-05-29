<?php

namespace App\Filament\Resources\FinancialListResource\Pages;

use App\Filament\Resources\FinancialListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFiles extends ListRecords
{
    protected static string $resource = FinancialListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
