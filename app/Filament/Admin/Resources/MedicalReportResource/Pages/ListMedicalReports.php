<?php

namespace App\Filament\Admin\Resources\MedicalReportResource\Pages;

use App\Filament\Admin\Resources\MedicalReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedicalReports extends ListRecords
{
    protected static string $resource = MedicalReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
