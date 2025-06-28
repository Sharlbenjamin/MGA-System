<?php

namespace App\Filament\Doctor\Resources\FileResource\Pages;

use App\Filament\Doctor\Resources\FileResource;
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
                'dob' => $data['patient_dob'] ?? null,
                'gender' => $data['patient_gender'] ?? null,
            ]);
            
            $data['patient_id'] = $patient->id;
        }

        return $data;
    }
}
