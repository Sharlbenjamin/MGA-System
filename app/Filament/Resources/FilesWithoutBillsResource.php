<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FilesWithoutBillsResource\Pages;
use App\Models\File;
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

class FilesWithoutBillsResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Files without Bills';
    protected static ?string $modelLabel = 'File without Bills';
    protected static ?string $pluralModelLabel = 'Files without Bills';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Assisted')
            ->whereDoesntHave('bills')
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Assisted')
                ->whereDoesntHave('bills')
                ->with(['patient.client', 'providerBranch.provider', 'country', 'city', 'serviceType']))
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
                Tables\Columns\TextColumn::make('provider_branch_info')
                    ->label('Provider & Branch')
                    ->formatStateUsing(function ($record) {
                        if (!$record->providerBranch) {
                            return 'No Provider Branch Assigned';
                        }
                        
                        $providerName = $record->providerBranch->provider?->name ?? 'Provider N/A';
                        $branchName = $record->providerBranch->branch_name ?? 'Branch N/A';
                        return $providerName . ' - ' . $branchName;
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('providerBranch.provider.name')
                    ->label('Provider')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('provider_branch_id')
                    ->relationship('providerBranch', 'branch_name')
                    ->label('Provider Branch')
                    ->searchable(),
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
                Action::make('upload_bill_create')
                    ->label('Upload Bill')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Bill Document')
                    ->modalDescription('Upload a bill document for this file.')
                    ->modalSubmitActionLabel('Upload Bill')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Bill Name')
                            ->required()
                            ->default(fn (File $record) => $record->mga_reference . '-Bill-01'),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->required()
                            ->prefix('€'),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Due Date')
                            ->required()
                            ->default(now()->addDays(60)),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Unpaid' => 'Unpaid',
                                'Partial' => 'Partial',
                                'Paid' => 'Paid'
                            ])
                            ->default('Unpaid')
                            ->required(),
                        Forms\Components\FileUpload::make('file_bill_document')
                            ->label('Upload Bill Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->nullable()
                            ->disk('public')
                            ->directory('bills')
                            ->visibility('public')
                            ->helperText('Upload the bill document (PDF or image) - Optional')
                            ->storeFileNamesIn('original_filename')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->maxFiles(1),
                    ])
                    ->action(function (File $record, array $data) {
                        try {
                            // Create the bill record first
                            $bill = new \App\Models\Bill([
                                'file_id' => $record->id,
                                'name' => $data['name'],
                                'total_amount' => $data['total_amount'],
                                'due_date' => $data['due_date'],
                                'status' => $data['status'],
                            ]);
                            
                            $bill->save();

                            // Handle the uploaded file if provided
                            if (isset($data['file_bill_document']) && !empty($data['file_bill_document'])) {
                                $uploadedFile = $data['file_bill_document'];
                                
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
                                    $fileName = 'Bill ' . $record->mga_reference . ' - ' . $record->patient->name . '.' . $originalExtension;
                                    Log::info('Bill file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                    
                                    // Upload to Google Drive using the service
                                    $uploadService = new \App\Services\UploadBillToGoogleDrive(new \App\Services\GoogleDriveFolderService());
                                    $uploadResult = $uploadService->uploadBillToGoogleDrive($content, $fileName, $bill);
                                    
                                    if ($uploadResult) {
                                        Log::info('Bill uploaded to Google Drive successfully:', ['result' => $uploadResult]);
                                        
                                        // Update the bill record with the Google Drive link
                                        $bill->bill_google_link = $uploadResult;
                                        $bill->save();
                                        
                                        Notification::make()
                                            ->success()
                                            ->title('Bill uploaded successfully')
                                            ->body('Bill document has been uploaded to Google Drive and created.')
                                            ->send();
                                    } else {
                                        Log::error('Failed to upload bill to Google Drive');
                                        Notification::make()
                                            ->danger()
                                            ->title('Google Drive upload failed')
                                            ->body('The bill was created but failed to upload to Google Drive.')
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
                            } else {
                                // No document uploaded, just create the bill
                                Notification::make()
                                    ->success()
                                    ->title('Bill created successfully')
                                    ->body('Bill has been created without a document.')
                                    ->send();
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
            'index' => Pages\ListFilesWithoutBills::route('/'),
        ];
    }
} 