<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Patient;

class CreateFile extends CreateRecord
{
    protected static string $resource = FileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['new_patient']) {
            $patient = Patient::create([
                'name' => $data['patient_name'],
                'client_id' => $data['client_id'],
            ]);
            
            $data['patient_id'] = $patient->id;
        }

        return $data;
    }
}
