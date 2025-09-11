<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Models\Client;
use App\Models\ServiceType;
use App\Services\OcrService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\ListRecords;

class ListFiles extends ListRecords
{
    protected static string $resource = FileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('extract_patient_data')
                ->label('Extract Patient Data')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->modalHeading('Extract Patient Information')
                ->modalDescription('Paste text to extract patient information')
                ->modalSubmitActionLabel('Process & Continue')
                ->modalCancelActionLabel('Cancel')
                                   ->form([
                       Section::make('Extract Patient Information')
                           ->description('Select a client and paste text to extract patient information')
                           ->schema([
                               Select::make('client_id')
                                   ->label('Client')
                                   ->options(Client::where('status', 'Active')->pluck('company_name', 'id'))
                                   ->required()
                                   ->searchable()
                                   ->preload()
                                   ->helperText('Select the client for this patient'),
                               
                               Select::make('language')
                                   ->label('Document Language')
                                   ->options([
                                       'english' => 'English',
                                       'spanish' => 'Spanish'
                                   ])
                                   ->default('english')
                                   ->required()
                                   ->helperText('Select the language of the document to improve extraction accuracy'),
                               
                               Textarea::make('text_input')
                                   ->label('Patient Information Text')
                                   ->rows(10)
                                   ->placeholder('Paste the patient information text here...')
                                   ->helperText('Paste the text containing patient information. The system will automatically extract relevant fields.')
                                   ->required()
                                   ->columnSpanFull()
                           ])
                           ->columns(1)
                   ])
                ->action(function (array $data) {
                    // Validate required fields
                    if (empty($data['client_id'])) {
                        Notification::make()
                            ->danger()
                            ->title('Missing Client')
                            ->body('Please select a client first.')
                            ->send();
                        return;
                    }
                    
                    // Check if text input is provided
                    if (empty($data['text_input'])) {
                        Notification::make()
                            ->danger()
                            ->title('Missing Input')
                            ->body('Please paste text containing patient information.')
                            ->send();
                        return;
                    }
                    
                    try {
                        // Get the OCR service
                        $ocrService = app(OcrService::class);
                        
                        // Process text input with language parameter
                        $extractedData = $ocrService->extractTextFromString($data['text_input'], $data['language'] ?? 'english');
                        
                        // Clean the extracted data
                        $cleanedData = $ocrService->cleanExtractedData($extractedData);
                        
                        // Determine gender from name
                        $gender = $ocrService->determineGenderFromName($cleanedData['patient_name']);
                        
                        // Store the extracted data in session for the create page
                        session([
                            'ocr_extracted_data' => [
                                'client_id' => $data['client_id'],
                                'patient_name' => $cleanedData['patient_name'],
                                'date_of_birth' => $cleanedData['date_of_birth'],
                                'client_reference' => $cleanedData['client_reference'],
                                'patient_address' => $cleanedData['patient_address'],
                                'symptoms' => $cleanedData['symptoms'],
                                'extra_field' => $cleanedData['extra_field'],
                                'phone' => $cleanedData['phone'],
                                'country' => $cleanedData['country'],
                                'city' => $cleanedData['city'],
                                'gender' => $gender,
                            ]
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Data Processed Successfully')
                            ->body('Redirecting to create file page with extracted data...')
                            ->send();
                        
                        // Redirect to the create file page
                        return redirect()->to(FileResource::getUrl('create'));
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Processing Failed')
                            ->body('Failed to process the data: ' . $e->getMessage())
                            ->send();
                    }
                })
                ->modalWidth('4xl'),
        ];
    }
    
    protected function findOrCreatePatient($clientId, $data, $gender)
    {
        // Check if patient already exists
        $existingPatient = \App\Models\Patient::where('name', $data['patient_name'])
            ->where('client_id', $clientId)
            ->first();
        
        if ($existingPatient) {
            return $existingPatient;
        }
        
        // Create new patient
        return \App\Models\Patient::create([
            'name' => $data['patient_name'],
            'client_id' => $clientId,
            'dob' => $data['date_of_birth'] ?: null,
            'gender' => $gender,
        ]);
    }
    
    protected function createFile($patient, $data, $formData)
    {
        return \App\Models\File::create([
            'patient_id' => $patient->id,
            'client_reference' => $data['client_reference'],
            'service_type_id' => $formData['service_type_id'],
            'address' => $data['patient_address'],
            'symptoms' => $data['symptoms'],
            'status' => 'New',
            'mga_reference' => \App\Models\File::generateMGAReference($patient->id, 'patient'),
        ]);
    }
}
