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
                ->modalSubmitAction(false)
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
                ->extraModalFooterActions([
                    Actions\Action::make('process_image')
                        ->label('Process Image')
                        ->color('warning')
                        ->icon('heroicon-o-cog')
                        ->action(function (array $data) {
                            // Debug: Show what we received
                            $screenshotData = $data['screenshot'] ?? 'NOT_SET';
                            $screenshotType = gettype($screenshotData);
                            
                            // Validate required fields for processing
                            $hasScreenshot = !empty($data['screenshot']) && 
                                           (is_string($data['screenshot']) || 
                                            (is_array($data['screenshot']) && !empty($data['screenshot'])));
                            
                            if (!$hasScreenshot) {
                                Notification::make()
                                    ->danger()
                                    ->title('Missing Screenshot')
                                    ->body("Screenshot data: {$screenshotData} (type: {$screenshotType}). Please upload a screenshot first.")
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
                                
                                // Update the form with extracted data
                                $this->form->fill([
                                    'patient_name' => $cleanedData['patient_name'],
                                    'date_of_birth' => $cleanedData['date_of_birth'],
                                    'client_reference' => $cleanedData['client_reference'],
                                    'patient_address' => $cleanedData['patient_address'],
                                    'symptoms' => $cleanedData['symptoms'],
                                    'extra_field' => $cleanedData['extra_field'],
                                ]);
                                
                                Notification::make()
                                    ->success()
                                    ->title('Image Processed Successfully')
                                    ->body('Text has been extracted from the image. Please review and edit the information below.')
                                    ->send();
                                    
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Processing Failed')
                                    ->body('Failed to process the image: ' . $e->getMessage())
                                    ->send();
                            }
                        })
                        ->visible(fn () => true),
                    
                    Actions\Action::make('create_file')
                        ->label('Create File')
                        ->color('success')
                        ->icon('heroicon-o-plus')
                        ->action(function (array $data) {
                            // Validate required fields for file creation
                            if (empty($data['patient_name'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('Missing Patient Name')
                                    ->body('Please process the image first or enter a patient name.')
                                    ->send();
                                return;
                            }
                            
                            if (empty($data['service_type_id'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('Missing Service Type')
                                    ->body('Please select a service type.')
                                    ->send();
                                return;
                            }
                            
                            if (empty($data['client_id'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('Missing Client')
                                    ->body('Please select a client.')
                                    ->send();
                                return;
                            }
                            
                            try {
                                DB::beginTransaction();
                                
                                // Get the OCR service for gender determination
                                $ocrService = app(OcrService::class);
                                
                                // Determine gender from name
                                $gender = $ocrService->determineGenderFromName($data['patient_name']);
                                
                                // Find or create patient
                                $patient = $this->findOrCreatePatient($data['client_id'], $data, $gender);
                                
                                // Create the file
                                $file = $this->createFile($patient, $data, $data);
                                
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
                        ->visible(fn () => true),
                ])
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
