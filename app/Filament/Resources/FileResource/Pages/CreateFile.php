<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Patient;
use Filament\Notifications\Notification;

class CreateFile extends CreateRecord
{
    protected static string $resource = FileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['new_patient']) {
            // Check for duplicate patient
            $duplicate = Patient::findDuplicate($data['patient_name'], $data['client_id']);
            
            if ($duplicate) {
                // Show notification about duplicate
                Notification::make()
                    ->warning()
                    ->title('Duplicate Patient Found')
                    ->body("A patient named '{$data['patient_name']}' already exists for this client. Using existing patient.")
                    ->send();

                // Use the existing patient instead of creating a new one
                $data['patient_id'] = $duplicate->id;
            } else {
                // Create new patient
                $patient = Patient::create([
                    'name' => $data['patient_name'],
                    'client_id' => $data['client_id'],
                    'dob' => $data['patient_dob'] ?? null,
                    'gender' => $data['patient_gender'] ?? null,
                ]);
                
                $data['patient_id'] = $patient->id;
            }
        }

        return $data;
    }
}
