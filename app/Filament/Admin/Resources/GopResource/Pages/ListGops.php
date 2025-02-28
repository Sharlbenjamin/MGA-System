<?php

namespace App\Filament\Admin\Resources\GopResource\Pages;

use App\Filament\Admin\Resources\GopResource;
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
