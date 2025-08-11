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
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('bill_google_link')->orWhere('bill_google_link', ''))
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
                Action::make('upload_bill_doc')
                    ->label('Upload Bill Doc')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Bill $record): string => "Upload Bill for {$record->file->mga_reference} (ID: {$record->id})")
                    ->modalDescription(fn (Bill $record): string => "Patient: {$record->file->patient->name} - Bill: {$record->name} (Record ID: {$record->id})")

                    ->extraAttributes([
                        'data-action-name' => 'upload_bill_doc',
                    ])
                    ->modalSubmitActionLabel('Upload Document')
                    ->form([
                        Forms\Components\FileUpload::make('bill_document')
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
                        // Debug: Log the record being processed
                        Log::info('Bill upload action called with record:', [
                            'record_id' => $record->id,
                            'record_name' => $record->name,
                            'file_reference' => $record->file->mga_reference ?? 'N/A',
                            'patient_name' => $record->file->patient->name ?? 'N/A'
                        ]);
                        
                        try {
                            if (!isset($data['bill_document']) || empty($data['bill_document'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['bill_document'];
                            
                            // Log the uploaded file data for debugging
                            Log::info('Bill doc upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
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
                                    Log::error('Bill doc file not found in storage:', ['path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Generate the proper filename format
                                $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                $fileName = 'Bill ' . $record->name . ' ' . $record->file->mga_reference . ' - ' . $record->file->patient->name . '.' . $originalExtension;
                                Log::info('Bill doc file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Upload to Google Drive using the service
                                $uploadService = new \App\Services\UploadBillToGoogleDrive(new \App\Services\GoogleDriveFolderService());
                                $uploadResult = $uploadService->uploadBillToGoogleDrive($content, $fileName, $record);
                                
                                if ($uploadResult) {
                                    Log::info('Bill uploaded to Google Drive successfully:', ['result' => $uploadResult]);
                                    
                                    // Update the Bill record with the Google Drive link
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
                Tables\Actions\Action::make('upload_doc')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.files.edit', $record->file) . '#bills')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->label('Upload Doc')
                    ->color('success'),
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