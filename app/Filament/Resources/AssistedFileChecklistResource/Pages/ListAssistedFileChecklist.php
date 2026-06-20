<?php

namespace App\Filament\Resources\AssistedFileChecklistResource\Pages;

use App\Filament\Resources\AssistedFileChecklistResource;
use Filament\Resources\Pages\ListRecords;

class ListAssistedFileChecklist extends ListRecords
{
    protected static string $resource = AssistedFileChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
