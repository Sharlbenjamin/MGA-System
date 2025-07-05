<?php

namespace App\Filament\Resources\FilesWithoutGopResource\Pages;

use App\Filament\Resources\FilesWithoutGopResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFilesWithoutGops extends ListRecords
{
    protected static string $resource = FilesWithoutGopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing files without GOP
        ];
    }
} 