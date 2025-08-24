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
use Filament\Forms\Components\Tabs;
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
                ->label('Extract Patient Data')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->modalHeading('Extract Patient Information')
                ->modalDescription('Upload a screenshot or paste text to extract patient information')
                ->modalSubmitActionLabel('Process & Continue')
                ->modalCancelActionLabel('Cancel')
                                   ->form([
                       Section::make('Extract Patient Information')
                           ->description('Select a client and either upload a screenshot or paste text to extract patient information')
                           ->schema([
                               Select::make('client_id')
                                   ->label('Client')
                                   ->options(Client::where('status', 'Active')->pluck('company_name', 'id'))
                                   ->required()
                                   ->searchable()
                                   ->preload()
                                   ->helperText('Select the client for this patient'),
                               
                               Tabs::make('Input Method')
                                   ->tabs([
                                       Tabs\Tab::make('Upload Screenshot')
                                           ->schema([
                                               FileUpload::make('screenshot')
                                                   ->label('Screenshot')
                                                   ->image()
                                                   ->imageEditor()
                                                   ->maxSize(10240) // 10MB
                                                   ->disk('public')
                                                   ->directory('screenshots')
                                                   ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                                                   ->helperText('Upload a clear screenshot of the patient information. Supported formats: JPG, PNG. Max size: 10MB.')
                                           ]),
                                       Tabs\Tab::make('Paste Text')
                                           ->schema([
                                               Textarea::make('text_input')
                                                   ->label('Patient Information Text')
                                                   ->rows(10)
                                                   ->placeholder('Paste the patient information text here...')
                                                   ->helperText('Paste the text containing patient information. The system will automatically extract relevant fields.')
                                           ])
                                   ])
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
                    
                    // Check if either screenshot or text input is provided
                    if (empty($data['screenshot']) && empty($data['text_input'])) {
                        Notification::make()
                            ->danger()
                            ->title('Missing Input')
                            ->body('Please either upload a screenshot or paste text containing patient information.')
                            ->send();
                        return;
                    }
                    
                    try {
                        // Get the OCR service
                        $ocrService = app(OcrService::class);
                        
                        // Process screenshot if provided
                        if (!empty($data['screenshot'])) {
                            $screenshotPath = is_array($data['screenshot']) ? $data['screenshot'][0] : $data['screenshot'];
                            $imagePath = Storage::disk('public')->path($screenshotPath);
                            $extractedData = $ocrService->extractTextFromImage($imagePath);
                        } else {
                            // Process text input if provided
                            $extractedData = $ocrService->extractTextFromString($data['text_input'] ?? '');
                        }
                        
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
