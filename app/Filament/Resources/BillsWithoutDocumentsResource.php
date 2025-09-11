<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillsWithoutDocumentsResource\Pages;
use App\Models\Bill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\UploadBillToGoogleDrive;
use App\Services\GoogleDriveFolderService;

class BillsWithoutDocumentsResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Workflow';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Bills without documents';
    protected static ?string $modelLabel = 'Bill without documents';
    protected static ?string $pluralModelLabel = 'Bills without documents';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('bill_google_link')->orWhere('bill_google_link', '')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('file_id')
                    ->relationship('file', 'mga_reference')
                    ->required(),
                Forms\Components\Select::make('provider_id')
                    ->relationship('provider', 'name')
                    ->required(),
                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'branch_name')
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('bill_google_link')
                    ->label('Google Drive Link')
                    ->url()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->whereNull('bill_google_link')->orWhere('bill_google_link', '');
                
                // Add debugging to see what records are being loaded
                Log::info('BillsWithoutDocuments query executed', [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);
                
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('File Reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Provider')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Bill Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Sent' => 'info',
                        'Unpaid' => 'warning',
                        'Partial' => 'warning',
                        'Paid' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_file')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.files.edit', $record->file))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Action::make('test_modal')
                    ->label('Test Modal')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Bill $record): string => "Test Modal for {$record->file->mga_reference}")
                    ->modalDescription(fn (Bill $record): string => "This is a test modal for record ID: {$record->id}")
                    ->modalSubmitActionLabel('Test')
                    ->action(function (Bill $record) {
                        // Get the fresh record to ensure we have the latest data
                        $freshRecord = Bill::with(['file.patient'])->find($record->id);
                        
                        if (!$freshRecord) {
                            Notification::make()
                                ->danger()
                                ->title('Record not found')
                                ->body('The record could not be found.')
                                ->send();
                            return;
                        }
                        
                        Log::info('Test modal action triggered for record:', [
                            'record_id' => $freshRecord->id,
                            'file_reference' => $freshRecord->file->mga_reference,
                            'patient_name' => $freshRecord->file->patient->name ?? 'N/A'
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Test Modal Working')
                            ->body("Modal for record {$freshRecord->id} - File: {$freshRecord->file->mga_reference} - Patient: {$freshRecord->file->patient->name}")
                            ->send();
                    }),
                Action::make('debug_record')
                    ->label('Debug Record')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Debug Information')
                    ->modalDescription(fn (Bill $record): string => "Record ID: {$record->id}\nFile Reference: {$record->file->mga_reference}\nPatient: {$record->file->patient->name}")
                    ->modalSubmitActionLabel('OK')
                    ->action(function (Bill $record) {
                        // Get the fresh record to ensure we have the latest data
                        $freshRecord = Bill::with(['file.patient'])->find($record->id);
                        
                        if (!$freshRecord) {
                            Notification::make()
                                ->danger()
                                ->title('Record not found')
                                ->body('The record could not be found.')
                                ->send();
                            return;
                        }
                        
                        Log::info('Debug action triggered for record:', [
                            'record_id' => $freshRecord->id,
                            'file_reference' => $freshRecord->file->mga_reference,
                            'patient_name' => $freshRecord->file->patient->name ?? 'N/A'
                        ]);
                        
                        Notification::make()
                            ->info()
                            ->title('Debug Info')
                            ->body("Record ID: {$freshRecord->id} - File: {$freshRecord->file->mga_reference} - Patient: {$freshRecord->file->patient->name}")
                            ->send();
                    }),
                Action::make('upload_bill_doc')
                    ->label('Upload Bill Document')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Bill $record): string => "Upload Bill for {$record->file->mga_reference}")
                    ->modalDescription(fn (Bill $record): string => "Patient: {$record->file->patient->name} - Upload the bill document to Google Drive.")
                    ->modalSubmitActionLabel('Upload')
                    ->modalWidth('lg')
                    ->closeModalByClickingAway(false)
                    ->form([
                        Forms\Components\Select::make('selected_bill_id')
                            ->label('Select Bill to Upload')
                            ->options(function () {
                                // Get all bills without documents
                                $bills = \App\Models\Bill::whereNull('bill_google_link')
                                    ->orWhere('bill_google_link', '')
                                    ->with(['file.patient', 'provider', 'branch'])
                                    ->get();
                                
                                return $bills->mapWithKeys(function ($bill) {
                                    $label = "ID: {$bill->id} - {$bill->file->mga_reference} - {$bill->file->patient->name} - {$bill->name}";
                                    return [$bill->id => $label];
                                });
                            })
                            ->searchable()
                            ->required()
                            ->default(fn (Bill $record) => $record->id)
                            ->helperText('Choose which bill to upload the document for'),
                        Forms\Components\FileUpload::make('bill_relation_document')
                            ->label('Upload Bill Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('public')
                            ->directory('bills')
                            ->visibility('public')
                            ->helperText('Upload the bill document (PDF or image)')
                            ->storeFileNamesIn('original_filename')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->maxFiles(1),
                    ])
                    ->action(function (Bill $record, array $data) {
                        try {
                            // Get the selected bill
                            $selectedBillId = $data['selected_bill_id'] ?? $record->id;
                            $selectedBill = \App\Models\Bill::with(['file.patient'])->find($selectedBillId);
                            
                            if (!$selectedBill) {
                                Notification::make()
                                    ->danger()
                                    ->title('Bill not found')
                                    ->body('The selected bill could not be found.')
                                    ->send();
                                return;
                            }
                            
                            // Add debugging information
                            Log::info('Upload bill action triggered:', [
                                'original_record_id' => $record->id,
                                'selected_bill_id' => $selectedBillId,
                                'selected_bill_file_reference' => $selectedBill->file->mga_reference,
                                'selected_bill_patient_name' => $selectedBill->file->patient->name,
                                'data_keys' => array_keys($data),
                                'action_name' => 'upload_bill_doc',
                                'timestamp' => now()->toISOString()
                            ]);
                            
                            // Also log the record details to ensure it's the correct one
                            Log::info('Selected bill details:', [
                                'id' => $selectedBill->id,
                                'name' => $selectedBill->name,
                                'file_id' => $selectedBill->file_id,
                                'provider_id' => $selectedBill->provider_id,
                                'branch_id' => $selectedBill->branch_id
                            ]);
                            
                            if (!isset($data['bill_relation_document']) || empty($data['bill_relation_document'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['bill_relation_document'];
                            
                            // Log the uploaded file data for debugging
                            Log::info('Bill upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
                            // If it's an array (multiple files), take the first one
                            if (is_array($uploadedFile)) {
                                $uploadedFile = $uploadedFile[0] ?? null;
                            }
                            
                            if (!$uploadedFile) {
                                Notification::make()
                                    ->danger()
                                    ->title('Invalid file data')
                                    ->body('The uploaded file data is invalid.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly using Storage facade
                            try {
                                // Get the file content using Storage facade
                                $content = Storage::disk('public')->get($uploadedFile);
                                
                                if ($content === false) {
                                    Log::error('Bill file not found in storage:', ['path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Generate the proper filename format
                                $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                $fileName = 'Bill ' . $selectedBill->file->mga_reference . ' - ' . $selectedBill->file->patient->name . '.' . $originalExtension;
                                Log::info('Bill file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Save to local storage using DocumentPathResolver
                                $resolver = app(\App\Services\DocumentPathResolver::class);
                                $localPath = $resolver->ensurePathFor($selectedBill->file, 'bills', $fileName);
                                \Illuminate\Support\Facades\Storage::disk('public')->put($localPath, $content);
                                
                                // Update bill with local document path
                                $selectedBill->bill_document_path = $localPath;

                                // Upload to Google Drive using the service (keep as secondary)
                                $uploadService = new \App\Services\UploadBillToGoogleDrive(new \App\Services\GoogleDriveFolderService());
                                $uploadResult = $uploadService->uploadBillToGoogleDrive($content, $fileName, $selectedBill);
                                
                                if ($uploadResult) {
                                    Log::info('Bill uploaded to Google Drive successfully:', ['result' => $uploadResult]);
                                    
                                    // Update the selected bill record with the Google Drive link
                                    $selectedBill->bill_google_link = $uploadResult;
                                }
                                
                                $selectedBill->save();
                                    
                                Notification::make()
                                    ->success()
                                    ->title('Bill document uploaded successfully')
                                    ->body("Bill document has been uploaded to Google Drive for: {$selectedBill->file->mga_reference}")
                                    ->send();
                            } else {
                                Log::error('Failed to upload bill to Google Drive');
                                Notification::make()
                                    ->danger()
                                    ->title('Google Drive upload failed')
                                    ->body('The file was saved locally but failed to upload to Google Drive.')
                                    ->send();
                            }
                                    
                            } catch (\Exception $e) {
                                Log::error('Bill file access error:', ['error' => $e->getMessage(), 'path' => $uploadedFile]);
                                Notification::make()
                                    ->danger()
                                    ->title('File access error')
                                    ->body('Error accessing uploaded file: ' . $e->getMessage())
                                    ->send();
                                return;
                            }
                        } catch (\Exception $e) {
                            Log::error('Bill upload error:', ['error' => $e->getMessage(), 'record' => $record->id]);
                            Notification::make()
                                ->danger()
                                ->title('Upload error')
                                ->body('An error occurred during upload: ' . $e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillsWithoutDocuments::route('/'),
        ];
    }
} 