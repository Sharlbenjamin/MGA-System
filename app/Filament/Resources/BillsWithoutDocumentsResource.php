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
    protected static ?string $navigationGroup = 'Stages';
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
                \Log::info('BillsWithoutDocuments query executed', [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);
                
                // Get the actual records to debug
                $records = $query->get();
                \Log::info('BillsWithoutDocuments records loaded', [
                    'count' => $records->count(),
                    'record_ids' => $records->pluck('id')->toArray(),
                    'file_references' => $records->pluck('file.mga_reference')->toArray()
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
                // Add a debug column to show the record ID
                Tables\Columns\TextColumn::make('id')
                    ->label('Record ID')
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
                    ->icon('heroicon-o-bug')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Bill $record): string => "Test Modal for {$record->file->mga_reference}")
                    ->modalDescription(fn (Bill $record): string => "This is a test modal for record ID: {$record->id}")
                    ->modalSubmitActionLabel('Test')
                    ->uniqueId(fn (Bill $record): string => "test_modal_{$record->id}")
                    ->action(function (Bill $record) {
                        Log::info('Test modal action triggered for record:', [
                            'record_id' => $record->id,
                            'file_reference' => $record->file->mga_reference
                        ]);
                        
                        Notification::make()
                            ->success()
                            ->title('Test Modal Working')
                            ->body("Modal for record {$record->id} is working correctly!")
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
                    ->uniqueId(fn (Bill $record): string => "debug_record_{$record->id}")
                    ->action(function (Bill $record) {
                        Notification::make()
                            ->info()
                            ->title('Debug Info')
                            ->body("Record ID: {$record->id} - File: {$record->file->mga_reference}")
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
                    ->uniqueId(fn (Bill $record): string => "upload_bill_doc_{$record->id}")
                    ->modalWidth('lg')
                    ->closeModalByClickingAway(false)
                    ->form([
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
                            // Add debugging information
                            Log::info('Upload bill action triggered for record:', [
                                'record_id' => $record->id,
                                'file_reference' => $record->file->mga_reference,
                                'patient_name' => $record->file->patient->name,
                                'data_keys' => array_keys($data),
                                'action_name' => 'upload_bill_doc',
                                'timestamp' => now()->toISOString()
                            ]);
                            
                            // Also log the record details to ensure it's the correct one
                            Log::info('Record details:', [
                                'id' => $record->id,
                                'name' => $record->name,
                                'file_id' => $record->file_id,
                                'provider_id' => $record->provider_id,
                                'branch_id' => $record->branch_id
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
                                $fileName = 'Bill ' . $record->file->mga_reference . ' - ' . $record->file->patient->name . '.' . $originalExtension;
                                Log::info('Bill file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Upload to Google Drive using the service
                                $uploadService = new \App\Services\UploadBillToGoogleDrive(new \App\Services\GoogleDriveFolderService());
                                $uploadResult = $uploadService->uploadBillToGoogleDrive($content, $fileName, $record);
                                
                                if ($uploadResult) {
                                    Log::info('Bill uploaded to Google Drive successfully:', ['result' => $uploadResult]);
                                    
                                    // Update the bill record with the Google Drive link
                                    $record->bill_google_link = $uploadResult;
                                    $record->save();
                                    
                                    Notification::make()
                                        ->success()
                                        ->title('Bill document uploaded successfully')
                                        ->body('Bill document has been uploaded to Google Drive.')
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