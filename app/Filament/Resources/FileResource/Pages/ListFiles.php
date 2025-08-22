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
                ->modalSubmitActionLabel('Process & Create File')
                ->modalCancelActionLabel('Cancel')
                ->form([
                    Section::make('Step 1: Select Client & Upload Image')
                        ->description('First select a client, then upload the screenshot')
                        ->schema([
                            Select::make('client_id')
                                ->label('Client')
                                ->options(Client::where('status', 'Active')->pluck('company_name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live(),
                            
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
                        ->columns(1),
                    
                    Section::make('Step 2: Review Extracted Data')
                        ->description('Review and edit the extracted information before creating the file')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('patient_name')
                                        ->label('Patient Name')
                                        ->required(),
                                    
                                    DatePicker::make('date_of_birth')
                                        ->label('Date of Birth')
                                        ->nullable(),
                                    
                                    TextInput::make('client_reference')
                                        ->label('Client Reference')
                                        ->nullable(),
                                    
                                    Select::make('service_type_id')
                                        ->label('Service Type')
                                        ->options(ServiceType::pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    
                                    Textarea::make('patient_address')
                                        ->label('Patient Address')
                                        ->rows(2)
                                        ->nullable(),
                                    
                                    Textarea::make('symptoms')
                                        ->label('Symptoms')
                                        ->rows(2)
                                        ->nullable(),
                                    
                                    Textarea::make('extra_field')
                                        ->label('Additional Information')
                                        ->rows(2)
                                        ->nullable(),
                                ])
                        ])
                        ->columns(1)
                ])
                ->action(function (array $data) {
                    try {
                        DB::beginTransaction();
                        
                        // Get the OCR service
                        $ocrService = app(OcrService::class);
                        
                        // Extract text from the uploaded image
                        $imagePath = Storage::disk('public')->path($data['screenshot']);
                        $extractedData = $ocrService->extractTextFromImage($imagePath);
                        
                        // Clean the extracted data
                        $cleanedData = $ocrService->cleanExtractedData($extractedData);
                        
                        // Determine gender from name
                        $gender = $ocrService->determineGenderFromName($cleanedData['patient_name']);
                        
                        // Find or create patient
                        $patient = $this->findOrCreatePatient($data['client_id'], $cleanedData, $gender);
                        
                        // Create the file
                        $file = $this->createFile($patient, $cleanedData, $data);
                        
                        DB::commit();
                        
                        // Show success notification
                        Notification::make()
                            ->success()
                            ->title('File Created Successfully')
                            ->body("File {$file->mga_reference} has been created for patient {$patient->name}")
                            ->send();
                        
                        // Redirect to the created file
                        return redirect()->to(FileResource::getUrl('view', ['record' => $file]));
                        
                    } catch (\Exception $e) {
                        DB::rollBack();
                        
                        Notification::make()
                            ->danger()
                            ->title('Error Creating File')
                            ->body('An error occurred while creating the file: ' . $e->getMessage())
                            ->send();
                        
                        return null;
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
