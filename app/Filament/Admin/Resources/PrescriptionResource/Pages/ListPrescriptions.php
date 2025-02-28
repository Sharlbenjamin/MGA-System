<?php

namespace App\Filament\Admin\Resources\PrescriptionResource\Pages;

use App\Filament\Admin\Resources\PrescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrescriptions extends ListRecords
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
