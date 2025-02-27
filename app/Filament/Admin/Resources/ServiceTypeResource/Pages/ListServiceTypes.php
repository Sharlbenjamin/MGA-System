<?php

namespace App\Filament\Admin\Resources\ServiceTypeResource\Pages;

use App\Filament\Admin\Resources\ServiceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceTypes extends ListRecords
{
    protected static string $resource = ServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
