<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GopWithoutDocsResource\Pages;
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

class GopWithoutDocsResource extends Resource
{
    protected static ?string $model = Gop::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'GOP without docs';
    protected static ?string $modelLabel = 'GOP without docs';
    protected static ?string $pluralModelLabel = 'GOP without docs';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('type', 'In')
            ->where(function ($query) {
                $query->whereNull('gop_google_drive_link')
                      ->orWhere('gop_google_drive_link', '');
            })
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
                Forms\Components\Select::make('file_id')
                    ->relationship('file', 'mga_reference')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'In' => 'In',
                        'Out' => 'Out',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                        'Updated' => 'Updated',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('gop_google_drive_link')
                    ->label('Google Drive Link')
                    ->url()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'In')
                ->where(function ($query) {
                    $query->whereNull('gop_google_drive_link')
                          ->orWhere('gop_google_drive_link', '');
                })
                ->whereHas('file', function ($fileQuery) {
                    $fileQuery->where('status', 'Assisted');
                }))
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
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'In' => 'success',
                        'Out' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Not Sent' => 'gray',
                        'Sent' => 'info',
                        'Updated' => 'warning',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'In' => 'In',
                        'Out' => 'Out',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                        'Updated' => 'Updated',
                        'Cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_file')
                    ->url(fn (Gop $record): string => route('filament.admin.resources.files.edit', $record->file))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Action::make('upload_gop_doc')
                    ->label('Upload GOP Doc')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload GOP Document')
                    ->modalDescription('Upload the GOP document for this record.')
                    ->modalSubmitActionLabel('Upload Document')
                    ->form([
                        Forms\Components\FileUpload::make('document')
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
                    ->action(function (Gop $record, array $data) {
                        try {
                            if (!isset($data['document']) || empty($data['document'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['document'];
                            
                            // Log the uploaded file data for debugging
                            Log::info('GOP doc upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
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
                                    Log::error('GOP doc file not found in storage:', ['path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Generate the proper filename format
                                $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                $fileName = 'GOP ' . $record->type . ' ' . $record->file->mga_reference . ' - ' . $record->file->patient->name . '.' . $originalExtension;
                                Log::info('GOP doc file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Upload to Google Drive using the service
                                $uploadService = new \App\Services\UploadGopToGoogleDrive(new \App\Services\GoogleDriveFolderService());
                                $uploadResult = $uploadService->uploadGopToGoogleDrive($content, $fileName, $record);
                                
                                if ($uploadResult) {
                                    Log::info('GOP uploaded to Google Drive successfully:', ['result' => $uploadResult]);
                                    
                                    // Update the GOP record with the Google Drive link
                                    $record->gop_google_drive_link = $uploadResult;
                                    $record->status = 'Sent';
                                    $record->save();
                                    
                                    Notification::make()
                                        ->success()
                                        ->title('GOP document uploaded successfully')
                                        ->body('GOP document has been uploaded to Google Drive.')
                                        ->send();
                                } else {
                                    Log::error('Failed to upload GOP to Google Drive');
                                    Notification::make()
                                        ->danger()
                                        ->title('Google Drive upload failed')
                                        ->body('The file was saved locally but failed to upload to Google Drive.')
                                        ->send();
                                }
                                    
                            } catch (\Exception $e) {
                                Log::error('GOP doc file access error:', ['error' => $e->getMessage(), 'path' => $uploadedFile]);
                                Notification::make()
                                    ->danger()
                                    ->title('File access error')
                                    ->body('Error accessing uploaded file: ' . $e->getMessage())
                                    ->send();
                                return;
                            }
                        } catch (\Exception $e) {
                            Log::error('GOP doc upload error:', ['error' => $e->getMessage(), 'record' => $record->id]);
                            Notification::make()
                                ->danger()
                                ->title('Upload error')
                                ->body('An error occurred during upload: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('upload_doc')
                    ->url(fn (Gop $record): string => route('filament.admin.resources.files.edit', $record->file) . '#gops')
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
            'index' => Pages\ListGopWithoutDocs::route('/'),
        ];
    }
} 