<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Support\GopInOfferForm;
use App\Models\Gop;
use App\Services\ClientOfferMessageFormatter;
use App\Services\UploadGopToGoogleDrive;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimized: eager loading (file.patient.client) for client column and generate/upload actions, pagination 10.
 * No explicit select: actions perform save(); unloaded columns would be overwritten.
 */
class GopRelationManager extends RelationManager
{
    protected static string $relationship = 'gops';
    protected static ?string $title = 'GOP';

    // Enable create, edit and delete operations
    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['file.patient.client', 'providerBranch', 'serviceType', 'items']))
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('type'),
                TextColumn::make('providerBranch.branch_name')->label('Provider')->placeholder('—'),
                TextColumn::make('effective_service_type_name')->label('Service')->placeholder('—'),
                TextColumn::make('offered_cost')->label('Cost')->placeholder('—'),
                TextColumn::make('file_fee')->label('Fee')->placeholder('—'),
                TextColumn::make('amount')->label('Total'),
                TextColumn::make('date')->date(),
                TextColumn::make('status')->badge()->color(fn (Gop $record, $state) => match (true) {
                    $record->type === 'In' && $state === Gop::IN_STATUS_ACCEPTED => 'success',
                    $record->type === 'In' && $state === Gop::IN_STATUS_REJECTED => 'danger',
                    $record->type === 'Out' && $state === Gop::OUT_STATUS_SENT => 'success',
                    default => 'warning',
                }),
            ])
            ->headerActions([
                // Create via modal action
                Action::make('create')->label('Add GOP')->icon('heroicon-o-plus')->modalHeading('Add GOP')
                ->modalWidth('7xl')
                ->modalButton('Create')
                    ->fillForm(fn (): array => GopInOfferForm::defaultInState($this->ownerRecord))
                    ->form(fn (): array => GopInOfferForm::schema($this->ownerRecord))
                    ->action(function (array $data) {
                        GopInOfferForm::persist($this->ownerRecord, $data);
                    })
            ])
            ->actions([
                Action::make('viewDocument')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Action::make('downloadDocument')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Action::make('viewGop')
                    ->label('View GOP Out')
                    ->icon('heroicon-o-document-text')
                    ->url(fn ($record) => route('gop.view', $record))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->type === 'Out'),
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

                        // Save to local storage using DocumentPathResolver (PRIMARY storage)
                        $resolver = app(\App\Services\DocumentPathResolver::class);
                        $localPath = $resolver->ensurePathFor($record->file, 'gops', $fileName);
                        \Illuminate\Support\Facades\Storage::disk('public')->put($localPath, $content);
                        
                        // Update GOP with local document path (PRIMARY)
                        $record->document_path = $localPath;

                        if ($record->type === 'Out') {
                            $record->status = Gop::OUT_STATUS_SENT;
                        }

                        // Clean up temporary file if it exists (from FileUpload component)
                        if (isset($uploadedFile) && $uploadedFile !== $localPath) {
                            try {
                                Storage::disk('public')->delete($uploadedFile);
                            } catch (\Exception $e) {
                                Log::warning('Could not delete temporary file', ['path' => $uploadedFile, 'error' => $e->getMessage()]);
                            }
                        }

                        // Upload to Google Drive (SECONDARY/BACKUP only)
                        $uploader = app(UploadGopToGoogleDrive::class);
                        $result = $uploader->uploadGopToGoogleDrive(
                            $content,
                            $fileName,
                            $record
                        );

                        if ($result !== false) {
                            $record->gop_google_drive_link = $result;
                        }

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
                    ->visible(fn (Gop $record) => $record->type === 'Out')
                    ->requiresConfirmation()
                    ->modalHeading('Send GOP')
                    ->modalDescription('Are you sure you want to send this GOP to the branch?')
                    ->modalSubmitActionLabel('Send GOP')
                    ->action(function ($record) {
                        $record->sendGopToBranch();
                    }),
                Action::make('copyOffer')
                    ->label('Copy offer')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->visible(fn (Gop $record): bool => $record->type === 'In')
                    ->fillForm(fn (Gop $record): array => [
                        'offer_sections' => app(ClientOfferMessageFormatter::class)->normalizeSections($record->offer_sections),
                        'message' => app(ClientOfferMessageFormatter::class)->formatOffer($record->file ?? $this->ownerRecord, $record),
                    ])
                    ->form(fn (Gop $record): array => GopInOfferForm::copyFormSchema(
                        $record->file ?? $this->ownerRecord,
                        $record,
                        ClientOfferMessageFormatter::MODE_OFFER,
                    ))
                    ->modalSubmitActionLabel('Copy')
                    ->action(function (Gop $record, array $data): void {
                        GopInOfferForm::copyToClipboard(
                            (string) ($data['message'] ?? ''),
                            'Client offer',
                            $this,
                        );
                    }),
                Action::make('copyRequest')
                    ->label('Copy GOP')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->visible(fn (Gop $record): bool => $record->type === 'In')
                    ->fillForm(fn (Gop $record): array => [
                        'offer_sections' => app(ClientOfferMessageFormatter::class)->normalizeSections($record->offer_sections),
                        'message' => app(ClientOfferMessageFormatter::class)->formatRequest($record->file ?? $this->ownerRecord, $record),
                    ])
                    ->form(fn (Gop $record): array => GopInOfferForm::copyFormSchema(
                        $record->file ?? $this->ownerRecord,
                        $record,
                        ClientOfferMessageFormatter::MODE_REQUEST,
                    ))
                    ->modalSubmitActionLabel('Copy')
                    ->action(function (Gop $record, array $data): void {
                        GopInOfferForm::copyToClipboard(
                            (string) ($data['message'] ?? ''),
                            'GOP request',
                            $this,
                        );
                    }),
                // Edit via modal action
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Edit GOP')
                    ->modalWidth('7xl')
                    ->modalButton('Update')
                    ->fillForm(fn (Gop $record) => GopInOfferForm::fillFromGop($record))
                    ->form(fn (): array => GopInOfferForm::schema($this->ownerRecord, isEdit: true))
                    ->action(function (Gop $record, array $data) {
                        GopInOfferForm::persist($this->ownerRecord, $data, $record);
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
