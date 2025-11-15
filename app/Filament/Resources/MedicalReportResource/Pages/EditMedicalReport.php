<?php

namespace App\Filament\Resources\MedicalReportResource\Pages;

use App\Filament\Resources\MedicalReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\MedicalReport;

class EditMedicalReport extends EditRecord
{
    protected static string $resource = MedicalReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            Actions\Action::make('view_document')
                ->label('View Document')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (MedicalReport $record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                ->openUrlInNewTab()
                ->visible(fn (MedicalReport $record) => $record->hasLocalDocument()),
            Actions\Action::make('download_document')
                ->label('Download Document')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (MedicalReport $record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                ->openUrlInNewTab()
                ->visible(fn (MedicalReport $record) => $record->hasLocalDocument()),
            Actions\Action::make('export')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $medicalReport = $this->getRecord();
                    $pdf = Pdf::loadView('pdf.medicalReport', ['medicalReport' => $medicalReport]);
                    $fileName = 'Medical_Report_' . $medicalReport->file->patient->name . '_' . ($medicalReport->date?->format('Y-m-d') ?? now()->format('Y-m-d')) . '.pdf';
                    
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $fileName
                    );
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
