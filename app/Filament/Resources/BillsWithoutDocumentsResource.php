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
use Filament\Forms\Components\FileUpload;
use App\Services\GoogleDriveFolderService;
use Filament\Tables\Columns\IconColumn;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('bill_google_link')->orWhere('bill_google_link', '')->orderBy('id', 'desc'))
            ->defaultSort('id', 'desc')
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
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
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.branch_name')
                    ->label('Branch')
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
                Tables\Columns\IconColumn::make('bill_google_link')
                    ->label('Google Drive')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (Bill $record): bool => !empty($record->bill_google_link)),
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
                Tables\Filters\SelectFilter::make('provider_id')
                    ->relationship('provider', 'name')
                    ->label('Provider'),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'branch_name')
                    ->label('Branch'),
            ])
            ->actions([
                Tables\Actions\Action::make('upload_bill')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->label('Upload Bill')
                    ->color('success')
                    ->visible(fn (Bill $record): bool => empty($record->bill_google_link))
                    ->modalHeading(fn (Bill $record): string => "Upload Bill for {$record->file->mga_reference}")
                    ->modalDescription(fn (Bill $record): string => "Patient: {$record->file->patient->name} - Bill: {$record->name}")
                    ->extraAttributes(fn (Bill $record): array => [
                        'data-record-id' => $record->id,
                        'data-record-name' => $record->name,
                        'data-action-name' => "upload_bill_{$record->id}",
                    ])
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Hidden::make('bill_id')
                            ->default(fn (Bill $record): string => $record->id),
                        FileUpload::make('bill_document')
                            ->label('Bill Document')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->helperText('Upload a PDF document. The file will be automatically renamed and uploaded to Google Drive.')
                            ->disk('public')
                            ->directory('temp-bills'),
                    ])
                    ->action(function (array $data, Bill $record): void {
                        // Get the bill ID from the form data to ensure we're working with the correct record
                        $billId = $data['bill_id'] ?? $record->id;
                        
                        // Fetch the bill fresh from the database to ensure we have the correct record
                        $bill = Bill::find($billId);
                        
                        if (!$bill) {
                            Log::error('Bill not found', ['bill_id' => $billId, 'record_id' => $record->id]);
                            Notification::make()
                                ->title('Upload Failed')
                                ->body('Bill not found. Please try again.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Add debugging to ensure we're working with the correct record
                        Log::info('Upload bill action triggered', [
                            'record_id' => $record->id,
                            'bill_id' => $bill->id,
                            'record_name' => $record->name,
                            'bill_name' => $bill->name,
                            'file_reference' => $bill->file->mga_reference ?? 'N/A',
                            'data_keys' => array_keys($data),
                            'action_name' => "upload_bill_{$record->id}",
                            'timestamp' => now()->toISOString()
                        ]);
                        
                        // Use the fetched bill instead of the passed record
                        $record = $bill;
                        
                        try {
                            $uploadService = new UploadBillToGoogleDrive(app(GoogleDriveFolderService::class));
                            
                            // Get the uploaded file
                            $filePath = $data['bill_document'];
                            
                            // Handle both array and string file paths
                            if (is_array($filePath)) {
                                $filePath = $filePath[0] ?? null;
                            }
                            
                            if (!$filePath) {
                                Notification::make()
                                    ->title('Upload Failed')
                                    ->body('No file was uploaded.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            $fileContent = Storage::disk('public')->get($filePath);
                            
                            if (!$fileContent) {
                                Notification::make()
                                    ->title('Upload Failed')
                                    ->body('Could not read the uploaded file.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            // Generate filename: Bill_{MGA_Reference}_{Bill_Name}.pdf
                            $fileName = "Bill_{$record->file->mga_reference}_{$record->name}.pdf";
                            
                            // Ensure the file has a Google Drive folder
                            if (empty($record->file->google_drive_link)) {
                                $folderService = app(GoogleDriveFolderService::class);
                                $folderService->generateGoogleDriveFolder($record->file);
                                $record->refresh();
                            }
                            
                            // Upload to Google Drive
                            $googleDriveLink = $uploadService->uploadBillToGoogleDrive($fileContent, $fileName, $record);
                            
                            if ($googleDriveLink) {
                                // Update the bill with the Google Drive link
                                $record->update(['bill_google_link' => $googleDriveLink]);
                                
                                // Clean up the temporary file
                                Storage::disk('public')->delete($filePath);
                                
                                Notification::make()
                                    ->title('Upload Successful')
                                    ->body("Bill '{$record->name}' for file '{$record->file->mga_reference}' has been uploaded to Google Drive successfully.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Upload Failed')
                                    ->body('Failed to upload bill to Google Drive. Please try again.')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Log::error('Bill upload failed: ' . $e->getMessage(), [
                                'record_id' => $record->id,
                                'record_name' => $record->name,
                                'file_reference' => $record->file->mga_reference ?? 'N/A'
                            ]);
                            
                            Notification::make()
                                ->title('Upload Failed')
                                ->body('An error occurred while uploading the bill: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalSubmitActionLabel('Upload to Google Drive'),

                Tables\Actions\Action::make('view_in_google_drive')
                    ->icon('heroicon-o-link')
                    ->label('View in Google Drive')
                    ->color('info')
                    ->visible(fn (Bill $record): bool => !empty($record->bill_google_link))
                    ->url(fn (Bill $record): string => $record->bill_google_link)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('view_file')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.files.edit', $record->file))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),

                Tables\Actions\Action::make('edit_bill')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.bills.edit', $record))
                    ->icon('heroicon-o-pencil')
                    ->label('Edit Bill')
                    ->color('primary'),

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