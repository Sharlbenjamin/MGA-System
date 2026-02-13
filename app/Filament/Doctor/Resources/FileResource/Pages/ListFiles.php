<?php

namespace App\Filament\Doctor\Resources\FileResource\Pages;

use App\Filament\Doctor\Resources\FileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFiles extends ListRecords
{
    protected static string $resource = FileResource::class;

    protected function getTablePollingInterval(): ?string
    {
        return '10s';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
