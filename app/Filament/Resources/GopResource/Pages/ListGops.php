<?php

namespace App\Filament\Resources\GopResource\Pages;

use App\Filament\Resources\GopResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGops extends ListRecords
{
    protected static string $resource = GopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
