<?php

namespace App\Filament\Resources\FileResource\RelationManagers;


use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\MedicalReportResource;
use Filament\Forms\Components\Grid;
use App\Models\MedicalReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Tables\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimized: eager loading (file.patient.client, file.providerBranch.provider) for client column and export/upload, pagination 10.
 * No explicit select: edit/upload actions use full record and save().
 */
class MedicalReportRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalReports';
    protected static ?string $title = 'Medical Reports ';

    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;
    
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['file.patient.client', 'file.providerBranch.provider']))
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('file.patient.client.company_name')
                    ->label('Client')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('diagnosis'),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('createModal')
                    ->label('Add New MR')
                    ->modalHeading('Add New Medical Report for '.$this->ownerRecord->patient->name)
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\Hidden::make('file_id')
                        ->default(fn () => $this->ownerRecord->getKey())
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('date')
                            ->required(),
                        \Filament\Forms\Components\Select::make('status')
                            ->options([
                                'Waiting'  => 'Waiting',
                                'Received' => 'Received',
                                'Not Sent' => 'Not Sent',
                                'Sent'     => 'Sent',
                            ])
                            ->default('Waiting')
                            ->required(),
                            \Filament\Forms\Components\Textarea::make('complain')->label('Complain')->nullable(),
                            \Filament\Forms\Components\Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
                            \Filament\Forms\Components\Textarea::make('history')->label('History')->nullable(),
                            \Filament\Forms\Components\TextInput::make('temperature')->label('Temperature')->nullable(),
                            \Filament\Forms\Components\TextInput::make('blood_pressure')->label('Blood Pressure')->nullable(),
                            \Filament\Forms\Components\TextInput::make('pulse')->label('Pulse')->nullable(),
                            \Filament\Forms\Components\Textarea::make('examination')->label('Examination')->nullable(),
                            \Filament\Forms\Components\Textarea::make('advice')->label('Advice')->nullable(),
                    ])
                    ->action(function (array $data) {
                        // Create the new Medical Report record.
                        \App\Models\MedicalReport::create($data);
                    })
                    ->successNotificationTitle('Medical Report Created'),
            ])
            ->actions([
                Actions\Action::make('upload_document')
                    ->label('Upload Document')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (MedicalReport $record): string => "Upload Medical Report Document")
                    ->modalDescription(fn (MedicalReport $record): string => "Patient: {$record->file->patient->name} - MGA Reference: {$record->file->mga_reference}")
                    ->modalSubmitActionLabel('Upload Document')
                    ->form([
                        Forms\Components\FileUpload::make('medical_report_document')
                            ->label('Upload Medical Report Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('public')
                            ->directory('medical-reports')
                            ->visibility('public')
                            ->helperText('Upload the medical report document (PDF or image)')
                            ->storeFileNamesIn('original_filename')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->maxFiles(1),
                    ])
                    ->action(function (MedicalReport $record, array $data) {
                        try {
                            if (!isset($data['medical_report_document']) || empty($data['medical_report_document'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['medical_report_document'];
                            
                            // Log the uploaded file data for debugging
                            Log::info('Medical report upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
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
                                    Log::error('Medical report file not found in storage:', ['path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Update the medical report with the file path
                                $record->document_path = $uploadedFile;
                                $record->save();
                                
                                Notification::make()
                                    ->success()
                                    ->title('Medical report document uploaded successfully')
                                    ->body('Medical report document has been uploaded.')
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Error uploading medical report file:', [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                                
                                Notification::make()
                                    ->danger()
                                    ->title('Error uploading file')
                                    ->body('An error occurred while uploading the file: ' . $e->getMessage())
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Log::error('Error in upload medical report action:', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body('An error occurred: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Actions\Action::make('viewDocument')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Actions\Action::make('downloadDocument')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Actions\Action::make('export')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->modalHeading('Export Medical Report PDF')
                ->modalDescription('Customize the doctor name for this medical report')
                ->modalWidth('md')
                ->form([
                    \Filament\Forms\Components\TextInput::make('doctor_name')
                        ->label('Doctor Name')
                        ->default(fn ($record) => $record->file->providerBranch?->provider?->name ?? 'N/A')
                        ->required()
                        ->placeholder('Enter doctor name')
                ])
                ->action(function ($record, array $data) {
                    $medicalReport = $record;
                    $customDoctorName = $data['doctor_name'];
                    
                    $pdf = Pdf::loadView('pdf.medicalReport', [
                        'medicalReport' => $medicalReport,
                        'customDoctorName' => $customDoctorName
                    ]);
                    $fileName = $medicalReport->file->patient->name . ' Medical Report ' . $medicalReport->file->mga_reference . '.pdf';
                    
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $fileName
                    );
                }),
                \Filament\Tables\Actions\Action::make('editMR')
                    ->label('Edit')
                    ->modalHeading('Edit Medical Report')
                    ->modalButton('Save')
                    ->form(function (\Illuminate\Database\Eloquent\Model $record): array {
                        return [
                            \Filament\Forms\Components\Hidden::make('file_id')
                                ->default($record->file_id),
                            \Filament\Forms\Components\DatePicker::make('date')
                                ->default($record->date)
                                ->required(),
                            \Filament\Forms\Components\Select::make('status')
                                ->default($record->status)
                                ->options([
                                    'Waiting'  => 'Waiting',
                                    'Received' => 'Received',
                                    'Not Sent' => 'Not Sent',
                                    'Sent'     => 'Sent',
                                ])
                                ->required(),
                                \Filament\Forms\Components\Textarea::make('complain')->label('Complain')->default($record->complain)->nullable(),
                                \Filament\Forms\Components\Textarea::make('diagnosis')->label('Diagnosis')->default($record->diagnosis)->nullable(),
                                \Filament\Forms\Components\Textarea::make('history')->label('History')->default($record->history)->nullable(),
                                \Filament\Forms\Components\TextInput::make('temperature')->label('Temperature')->default($record->temperature)->nullable(),
                                \Filament\Forms\Components\TextInput::make('blood_pressure')->label('Blood Pressure')->default($record->blood_pressure)->nullable(),
                                \Filament\Forms\Components\TextInput::make('pulse')->label('Pulse')->default($record->pulse)->nullable(),
                                \Filament\Forms\Components\Textarea::make('examination')->label('Examination')->default($record->examination)->nullable(),
                                \Filament\Forms\Components\Textarea::make('advice')->label('Advice')->default($record->advice)->nullable(),
                        ];
                    })
                    ->action(function (\Illuminate\Database\Eloquent\Model $record, array $data): void {
                        // Update the record with the new data.
                        $record->update($data);
                    }),
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
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}