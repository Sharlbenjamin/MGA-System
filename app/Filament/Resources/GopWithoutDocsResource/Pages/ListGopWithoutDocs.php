<?php

namespace App\Filament\Resources\GopWithoutDocsResource\Pages;

use App\Filament\Resources\GopWithoutDocsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGopWithoutDocs extends ListRecords
{
    protected static string $resource = GopWithoutDocsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing GOP without docs
        ];
    }
} 