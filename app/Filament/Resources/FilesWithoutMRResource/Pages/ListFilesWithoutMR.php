<?php

namespace App\Filament\Resources\FilesWithoutMRResource\Pages;

use App\Filament\Resources\FilesWithoutMRResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFilesWithoutMR extends ListRecords
{
    protected static string $resource = FilesWithoutMRResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing files without medical reports
        ];
    }
}
