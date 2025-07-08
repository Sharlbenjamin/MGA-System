<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use App\Filament\Resources\BillResource;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\FileResource\Pages;
use App\Filament\Resources\InvoiceResource;
use App\Models\Country;
use App\Models\File;
use App\Models\Bill;
use App\Models\Patient;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    protected static ?string $model = Bill::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable()->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Paid' => 'success',
                    'Unpaid' => 'warning',
                    'Partial' => 'gray',
                }),
                Tables\Columns\TextColumn::make('due_date')->sortable()->searchable()->date(),
                Tables\Columns\TextColumn::make('total_amount')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining Amount')
                    ->money('EUR')
                    ->state(fn ($record) => $record->total_amount - $record->paid_amount)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('bill_google_link')
                    ->label('Google Drive Link')
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state)
                    ->url(fn ($state) => str_starts_with($state, 'http') ? $state : "https://{$state}", true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
            ])
            ->actions([
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Bill Document')
                    ->modalDescription('Upload the bill document to Google Drive.')
                    ->modalSubmitActionLabel('Upload')
                    ->form([
                        Forms\Components\FileUpload::make('document')
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
                    ->action(function ($record, array $data = []) {
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
                                
                                // Here you would upload to Google Drive
                                // For now, we'll just update the bill with the file path
                                $record->bill_google_link = $uploadedFile;
                                $record->save();
                                
                                Notification::make()
                                    ->success()
                                    ->title('Bill document uploaded successfully')
                                    ->body('Bill document has been uploaded.')
                                    ->send();
                                    
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
                Action::make('editBill')
                    ->url(fn ($record) => BillResource::getUrl('edit', [
                        'record' => $record->id
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Action::make('createBill')->label('Create Bill')
                    ->openUrlInNewTab(false)
                    ->url(fn () => BillResource::getUrl('create', [
                        'file_id' => $this->ownerRecord->id
                    ])),
            ]);
    }
}
