<?php

namespace App\Filament\Admin\Resources\MedicalReportResource\Pages;

use App\Filament\Admin\Resources\MedicalReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMedicalReport extends EditRecord
{
    protected static string $resource = MedicalReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
