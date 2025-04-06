<?php

namespace App\Filament\Resources\FileFeeResource\Pages;

use App\Filament\Resources\FileFeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFileFees extends ListRecords
{
    protected static string $resource = FileFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
