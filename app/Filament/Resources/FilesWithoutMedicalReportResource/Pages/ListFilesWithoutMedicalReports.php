<?php

namespace App\Filament\Resources\FilesWithoutMedicalReportResource\Pages;

use App\Filament\Resources\FilesWithoutMedicalReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFilesWithoutMedicalReports extends ListRecords
{
    protected static string $resource = FilesWithoutMedicalReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since this is for viewing files without medical reports
        ];
    }
} 