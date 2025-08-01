<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use App\Services\UploadGopToGoogleDrive;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GopRelationManager extends RelationManager
{
    protected static string $relationship = 'gops'; // Make sure this matches your File model relationship name
    protected static ?string $title = 'GOP';

    // Enable create, edit and delete operations
    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('type'),
                TextColumn::make('file.patient.client.company_name')
                    ->label('Client')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount'),
                TextColumn::make('date')->date(),
                TextColumn::make('status')->badge()->color(fn($state) => $state === 'Sent' ? 'success' : 'danger'),
                TextColumn::make('gop_google_drive_link')
                    ->label('Google Drive Link')
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state)
                    ->url(fn ($state) => str_starts_with($state, 'http') ? $state : "https://{$state}", true),
            ])
            ->headerActions([
                // Create via modal action
                Action::make('create')->label('Add GOP')->icon('heroicon-o-plus')->modalHeading('Add GOP')
                ->modalButton('Create')
                    ->form([
                        // Use a hidden field to set file_id from the owner record
                        Hidden::make('file_id')->default(fn() => $this->ownerRecord->getKey()),
                        Select::make('type')
                            ->options([
                                'In'  => 'In',
                                'Out' => 'Out',
                            ])
                            ->required(),
                        TextInput::make('amount')->numeric()->required(),
                        DatePicker::make('date')->required(),
                        Select::make('status')->options(['Not Sent' => 'Not Sent', 'Sent' => 'Sent', 'Updated' => 'Updated', 'Cancelled' => 'Cancelled'])->default('Not Sent')->required(),
                        TextInput::make('gop_google_drive_link')->label('Google Drive Link')->nullable(),
                    ])
                    ->action(function (array $data) {
                        // Create the GOP record using the parent model's relation
                        $this->ownerRecord->gops()->create($data);
                    })
            ])
            ->actions([
                Action::make('viewGop')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('gop.view', $record))
                    ->openUrlInNewTab(),
                Action::make('generate')
                    ->label(fn ($record) => $record->type === 'Out' ? 'GOP Generate' : 'GOP Upload')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->type === 'Out' ? 'Generate GOP' : 'Upload GOP')
                    ->modalDescription(fn ($record) => $record->type === 'Out' 
                        ? 'This will generate and upload the GOP document to Google Drive.'
                        : 'This will upload the GOP document to Google Drive.')
                    ->modalSubmitActionLabel(fn ($record) => $record->type === 'Out' ? 'Generate' : 'Upload')
                    ->form(fn ($record) => $record->type === 'In' ? [
                        Forms\Components\FileUpload::make('gop_relation_document')
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
                    ] : [])
                    ->action(function ($record, array $data = []) {
                        try {
                            if ($record->type === 'Out') {
                                // Generate PDF for Out type
                                $pdf = Pdf::loadView('pdf.gop_out', ['gop' => $record]);
                                $content = $pdf->output();
                                $fileName = 'GOP in ' . $record->file->mga_reference . ' - ' . $record->file->patient->name . '.pdf';
                            } else {
                                // Upload existing document for In type
                                if (!isset($data['gop_relation_document']) || empty($data['gop_relation_document'])) {
                                    Notification::make()
                                        ->danger()
                                        ->title('No document uploaded')
                                        ->body('Please upload a document first.')
                                        ->send();
                                    return;
                                }

                                // Handle the uploaded file properly
                                $uploadedFile = $data['gop_relation_document'];
                                
                                // Log the uploaded file data for debugging
                                Log::info('Uploaded file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                                
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
                                        Log::error('File not found in storage:', ['path' => $uploadedFile]);
                                        Notification::make()
                                            ->danger()
                                            ->title('File not found')
                                            ->body('The uploaded file could not be found in storage.')
                                            ->send();
                                        return;
                                    }
                                    
                                    // Generate the proper filename format
                                    $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                    $fileName = 'GOP in ' . $record->file->mga_reference . ' - ' . $record->file->patient->name . '.' . $originalExtension;
                                    Log::info('File successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                } catch (\Exception $e) {
                                    Log::error('File access error:', ['error' => $e->getMessage(), 'path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File access error')
                                        ->body('Error accessing uploaded file: ' . $e->getMessage())
                                        ->send();
                                    return;
                                }
                            }

                        // Upload to Google Drive
                        $uploader = app(UploadGopToGoogleDrive::class);
                        $result = $uploader->uploadGopToGoogleDrive(
                            $content,
                            $fileName,
                            $record
                        );

                        if ($result === false) {
                            Notification::make()
                                ->danger()
                                ->title('Upload failed')
                                ->body('Check logs for more details')
                                ->send();
                            return;
                        }

                        // Update GOP with Google Drive link and status
                        $record->gop_google_drive_link = $result;
                        $record->status = 'Sent';
                        $record->save();

                        $actionType = $record->type === 'Out' ? 'generated and uploaded' : 'uploaded';
                        Notification::make()
                            ->success()
                            ->title("GOP {$actionType} successfully")
                            ->body('GOP has been uploaded to Google Drive.')
                            ->send();
                        } catch (\Exception $e) {
                            Log::error('GOP upload error:', ['error' => $e->getMessage(), 'record' => $record->id]);
                            Notification::make()
                                ->danger()
                                ->title('Upload error')
                                ->body('An error occurred during upload: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                // Add this new action before existing actions
                Action::make('sendToBranch')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send GOP')
                    ->modalDescription('Are you sure you want to send this GOP to the branch?')
                    ->modalSubmitActionLabel('Send GOP')
                    ->action(function ($record) {
                        $record->sendGopToBranch();
                    }),
                // Edit via modal action
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Edit GOP')
                    ->modalButton('Update')
                    ->form(function ($record) {
                        return [
                            // file_id can be hidden and unchanged
                            Hidden::make('file_id')->default($record->file_id),
                            Select::make('type')
                                ->options([
                                    'In'  => 'In',
                                    'Out' => 'Out',
                                ])->default($record->type)->required(),
                            TextInput::make('amount')->numeric()->default($record->amount)->required(),
                            DatePicker::make('date')->default($record->date)->required(),
                            Select::make('status')->options(['Not Sent' => 'Not Sent', 'Sent' => 'Sent', 'Updated' => 'Updated', 'Cancelled' => 'Cancelled'])->default($record->status)->required(),
                            TextInput::make('gop_google_drive_link')->label('Google Drive Link')->nullable(),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $record->update($data);
                    }),
                // Delete action
                \Filament\Tables\Actions\Action::make('deleteCustom')
                    ->label('Delete')
                    ->icon('heroicon-o-trash') // You can use any icon supported by Filament
                    ->color('danger')
                    ->requiresConfirmation() // Prompts a confirmation modal before deleting
                    ->action(function ($record) {
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
