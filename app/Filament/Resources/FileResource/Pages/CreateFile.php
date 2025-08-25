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
    
    public function mount(): void
    {
        parent::mount();
        
        // Check if we have OCR extracted data in session
        if (session()->has('ocr_extracted_data')) {
            $ocrData = session('ocr_extracted_data');
            
            // Generate MGA reference if client_id is provided
            $mgaReference = '';
            if (!empty($ocrData['client_id'])) {
                $mgaReference = \App\Models\File::generateMGAReference($ocrData['client_id'], 'client');
            }
            
            // Map country and city names to IDs
            $countryId = null;
            $cityId = null;
            
            if (!empty($ocrData['country'])) {
                $country = \App\Models\Country::where('name', 'like', '%' . $ocrData['country'] . '%')->first();
                $countryId = $country ? $country->id : null;
            }
            
            if (!empty($ocrData['city']) && $countryId) {
                $city = \App\Models\City::where('name', 'like', '%' . $ocrData['city'] . '%')
                    ->where('country_id', $countryId)
                    ->first();
                $cityId = $city ? $city->id : null;
            }
            
            // Pre-fill the form with OCR data
            $this->form->fill([
                'new_patient' => true,
                'patient_name' => $ocrData['patient_name'] ?? '',
                'patient_dob' => $ocrData['date_of_birth'] ?? '',
                'patient_gender' => $ocrData['gender'] ?? 'Female',
                'client_id' => $ocrData['client_id'] ?? '',
                'client_reference' => $ocrData['client_reference'] ?? '',
                'address' => $ocrData['patient_address'] ?? '',
                'symptoms' => $ocrData['symptoms'] ?? '',
                'phone' => $ocrData['phone'] ?? '',
                'service_type_id' => $ocrData['service_type'] ?? '',
                'country_id' => $countryId,
                'city_id' => $cityId,
                'status' => 'New',
                'mga_reference' => $mgaReference,
            ]);
            
            // Clear the session data
            session()->forget('ocr_extracted_data');
            
            // Show notification
            Notification::make()
                ->success()
                ->title('OCR Data Loaded')
                ->body('Data extracted from your screenshot has been pre-filled. Please review and complete the form.')
                ->send();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure status is set to "New" for new files
        $data['status'] = 'New';
        
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
