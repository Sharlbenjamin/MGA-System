<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Http;

class PatientNameInput extends TextInput
{
    protected string $view = 'filament.forms.components.patient-name-input';

    public function similarPatients(Get $get, Set $set): void
    {
        $name = $get('patient_name');
        $clientId = $get('client_id');

        if (strlen($name) < 2) {
            $set('similar_patients', []);
            return;
        }

        try {
            $response = Http::get('/api/patients/search-similar', [
                'name' => $name,
                'client_id' => $clientId
            ]);

            if ($response->successful()) {
                $set('similar_patients', $response->json('data', []));
            } else {
                $set('similar_patients', []);
            }
        } catch (\Exception $e) {
            $set('similar_patients', []);
        }
    }

    public function checkDuplicate(Get $get, Set $set): void
    {
        $name = $get('patient_name');
        $clientId = $get('client_id');

        if (empty($name) || empty($clientId)) {
            $set('duplicate_patient', null);
            return;
        }

        try {
            $response = Http::post('/api/patients/check-duplicate', [
                'name' => $name,
                'client_id' => $clientId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['is_duplicate']) {
                    $set('duplicate_patient', $data['duplicate_patient']);
                } else {
                    $set('duplicate_patient', null);
                }
            }
        } catch (\Exception $e) {
            $set('duplicate_patient', null);
        }
    }
} 