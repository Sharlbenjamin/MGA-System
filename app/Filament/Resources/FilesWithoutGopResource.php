<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FilesWithoutGopResource\Pages;
use App\Models\File;
use App\Models\Gop;
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

class FilesWithoutGopResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Files without GOP';
    protected static ?string $modelLabel = 'File without GOP';
    protected static ?string $pluralModelLabel = 'Files without GOP';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Assisted')
            ->whereDoesntHave('gops')
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('mga_reference')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('patient_id')
                    ->relationship('patient', 'name')
                    ->required(),
                Forms\Components\Select::make('country_id')
                    ->relationship('country', 'name')
                    ->required(),
                Forms\Components\Select::make('city_id')
                    ->relationship('city', 'name')
                    ->required(),
                Forms\Components\Select::make('service_type_id')
                    ->relationship('serviceType', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('service_date')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Assisted')->whereDoesntHave('gops'))
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_reference')
                    ->label('Client Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Client reference copied to clipboard')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('country.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'New' => 'gray',
                        'Handling' => 'warning',
                        'Available' => 'info',
                        'Confirmed' => 'primary',
                        'Assisted' => 'success',
                        'Hold' => 'danger',
                        'Cancelled' => 'danger',
                        'Void' => 'danger',
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
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                    ]),
                Tables\Filters\SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country'),
                Tables\Filters\SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('City'),
                Tables\Filters\SelectFilter::make('service_type_id')
                    ->relationship('serviceType', 'name')
                    ->label('Service Type'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (File $record): string => route('filament.admin.resources.files.edit', $record))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Action::make('upload_gop')
                    ->label('Upload GOP')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload GOP Document')
                    ->modalDescription('Upload a GOP document for this file.')
                    ->modalSubmitActionLabel('Upload GOP')
                    ->uniqueId(fn (File $record): string => 'upload_gop_' . $record->id)
                    ->form([
                        Forms\Components\Select::make('type')
                            ->options([
                                'In'  => 'In',
                                'Out' => 'Out',
                            ])
                            ->required()
                            ->default('In'),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix('€'),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Not Sent' => 'Not Sent',
                                'Sent' => 'Sent',
                                'Updated' => 'Updated',
                                'Cancelled' => 'Cancelled'
                            ])
                            ->default('Not Sent')
                            ->required(),
                        Forms\Components\FileUpload::make('file_gop_document')
                            ->label('Upload GOP Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('public')
                            ->directory('gops')
                            ->visibility('public')
                            ->helperText('Upload the GOP document (PDF or image)')
                            ->storeFileNamesIn('original_filename')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->maxFiles(1),
                    ])
                    ->action(function (File $record, array $data) {
                        try {
                            if (!isset($data['file_gop_document']) || empty($data['file_gop_document'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['file_gop_document'];
                            
                            // Log the uploaded file data for debugging
                            Log::info('GOP upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
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
                                    Log::error('GOP file not found in storage:', ['path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Generate the proper filename format
                                $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                $fileName = 'GOP ' . $data['type'] . ' ' . $record->mga_reference . ' - ' . $record->patient->name . '.' . $originalExtension;
                                Log::info('GOP file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Create the GOP record first
                                $gop = new \App\Models\Gop([
                                    'file_id' => $record->id,
                                    'type' => $data['type'],
                                    'amount' => $data['amount'],
                                    'date' => $data['date'],
                                    'status' => $data['status'],
                                ]);
                                
                                $gop->save();
                                
                                // Upload to Google Drive using the service
                                $uploadService = new \App\Services\UploadGopToGoogleDrive(new \App\Services\GoogleDriveFolderService());
                                $uploadResult = $uploadService->uploadGopToGoogleDrive($content, $fileName, $gop);
                                
                                if ($uploadResult) {
                                    Log::info('GOP uploaded to Google Drive successfully:', ['result' => $uploadResult]);
                                    
                                    // Update the GOP record with the Google Drive link
                                    $gop->gop_google_drive_link = $uploadResult;
                                    $gop->status = 'Sent';
                                    $gop->save();
                                    
                                    Notification::make()
                                        ->success()
                                        ->title('GOP uploaded successfully')
                                        ->body('GOP document has been uploaded to Google Drive and created.')
                                        ->send();
                                } else {
                                    Log::error('Failed to upload GOP to Google Drive');
                                    Notification::make()
                                        ->danger()
                                        ->title('Google Drive upload failed')
                                        ->body('The GOP was created but failed to upload to Google Drive.')
                                        ->send();
                                }
                                    
                            } catch (\Exception $e) {
                                Log::error('GOP file access error:', ['error' => $e->getMessage(), 'path' => $uploadedFile]);
                                Notification::make()
                                    ->danger()
                                    ->title('File access error')
                                    ->body('Error accessing uploaded file: ' . $e->getMessage())
                                    ->send();
                                return;
                            }
                        } catch (\Exception $e) {
                            Log::error('GOP upload error:', ['error' => $e->getMessage(), 'record' => $record->id]);
                            Notification::make()
                                ->danger()
                                ->title('Upload error')
                                ->body('An error occurred during upload: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('create_gop')
                    ->url(fn (File $record): string => route('filament.admin.resources.files.edit', $record) . '#gops')
                    ->icon('heroicon-o-plus')
                    ->label('Create GOP')
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
            'index' => Pages\ListFilesWithoutGops::route('/'),
        ];
    }
} 