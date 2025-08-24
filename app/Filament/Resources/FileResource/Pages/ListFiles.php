<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Models\Client;
use App\Models\ServiceType;
use App\Services\OcrService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\ListRecords;

class ListFiles extends ListRecords
{
    protected static string $resource = FileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('upload_screenshot')
                ->label('Upload Screenshot')
                ->icon('heroicon-o-camera')
                ->color('success')
                ->modalHeading('Upload Screenshot & Extract Data')
                ->modalDescription('Upload a screenshot and use OCR to extract patient information')
                ->modalSubmitActionLabel('Process Image & Continue')
                ->modalCancelActionLabel('Cancel')
                ->form([
                    Section::make('Upload Screenshot for OCR Processing')
                        ->description('Select a client and upload a screenshot to extract patient information')
                        ->schema([
                            Select::make('client_id')
                                ->label('Client')
                                ->options(Client::where('status', 'Active')->pluck('company_name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->helperText('Select the client for this patient'),
                            
                            FileUpload::make('screenshot')
                                ->label('Screenshot')
                                ->image()
                                ->imageEditor()
                                ->required()
                                ->maxSize(10240) // 10MB
                                ->disk('public')
                                ->directory('screenshots')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                                ->helperText('Upload a clear screenshot of the patient information. Supported formats: JPG, PNG. Max size: 10MB.')
                        ])
                        ->columns(1)
                ])
                ->action(function (array $data) {
                    // Validate required fields
                    if (empty($data['screenshot'])) {
                        Notification::make()
                            ->danger()
                            ->title('Missing Screenshot')
                            ->body('Please upload a screenshot first.')
                            ->send();
                        return;
                    }
                    
                    if (empty($data['client_id'])) {
                        Notification::make()
                            ->danger()
                            ->title('Missing Client')
                            ->body('Please select a client first.')
                            ->send();
                        return;
                    }
                    
                    try {
                        // Get the OCR service
                        $ocrService = app(OcrService::class);
                        
                        // Handle file upload path - it might be a string or array
                        $screenshotPath = is_array($data['screenshot']) ? $data['screenshot'][0] : $data['screenshot'];
                        
                        // Extract text from the uploaded image
                        $imagePath = Storage::disk('public')->path($screenshotPath);
                        $extractedData = $ocrService->extractTextFromImage($imagePath);
                        
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
                                'gender' => $gender,
                            ]
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Image Processed Successfully')
                            ->body('Redirecting to create file page with extracted data...')
                            ->send();
                        
                        // Redirect to the create file page
                        return redirect()->to(FileResource::getUrl('create'));
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Processing Failed')
                            ->body('Failed to process the image: ' . $e->getMessage())
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
