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
                        Forms\Components\FileUpload::make('document')
                            ->label('Upload GOP Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->helperText('Upload the GOP document (PDF or image)'),
                    ] : [])
                    ->action(function ($record, array $data = []) {
                        if ($record->type === 'Out') {
                            // Generate PDF for Out type
                            $pdf = Pdf::loadView('pdf.gop_out', ['gop' => $record]);
                            $content = $pdf->output();
                            $fileName = 'GOP Out ' . $record->file->mga_reference . ' - ' . $record->file->patient->name . '.pdf';
                        } else {
                            // Upload existing document for In type
                            if (!isset($data['document'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            $filePath = storage_path('app/public/' . $data['document']);
                            if (!file_exists($filePath)) {
                                Notification::make()
                                    ->danger()
                                    ->title('File not found')
                                    ->body('The uploaded file could not be found.')
                                    ->send();
                                return;
                            }

                            $content = file_get_contents($filePath);
                            $fileName = basename($data['document']);
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

                        // Update GOP status
                        $record->status = 'Sent';
                        $record->save();

                        $actionType = $record->type === 'Out' ? 'generated and uploaded' : 'uploaded';
                        Notification::make()
                            ->success()
                            ->title("GOP {$actionType} successfully")
                            ->body('GOP has been uploaded to Google Drive.')
                            ->send();
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
