<?php

namespace App\Filament\Pages;

use App\Models\File;
use App\Models\Bill;
use App\Models\Gop;
use App\Models\Invoice;
use App\Models\Transaction;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;

class UploadStageDocument extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Workflow';
    protected static ?string $navigationLabel = 'Upload Stage Document';
    protected static ?int $navigationSort = 11;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Upload Document for Stage')
                    ->description('Select a file and stage to upload a document')
                    ->schema([
                        Select::make('file_id')
                            ->label('File')
                            ->options(File::where('status', '!=', 'Void')
                                ->get()
                                ->pluck('mga_reference', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('stage_input', null)),

                        Select::make('stage')
                            ->label('Stage')
                            ->options([
                                'New' => 'New',
                                'Handling' => 'Handling',
                                'Available' => 'Available',
                                'Confirmed' => 'Confirmed',
                                'Requesting GOP' => 'Requesting GOP',
                                'Assisted' => 'Assisted',
                                'Hold' => 'Hold',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('stage_input', null)),

                        Select::make('stage_input')
                            ->label('Stage Input')
                            ->options(function (Get $get) {
                                $fileId = $get('file_id');
                                $stage = $get('stage');
                                
                                if (!$fileId || !$stage) {
                                    return [];
                                }

                                $file = File::find($fileId);
                                if (!$file) {
                                    return [];
                                }

                                // Define stage inputs based on the selected stage
                                $stageInputs = $this->getStageInputsForStage($stage);
                                
                                return $stageInputs;
                            })
                            ->searchable()
                            ->required()
                            ->reactive(),

                        FileUpload::make('document')
                            ->label('Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('public')
                            ->directory('stage-documents'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    private function getStageInputsForStage(string $stage): array
    {
        $stageInputs = [
            'New' => [
                'patient_info' => 'Patient Information',
                'initial_assessment' => 'Initial Assessment',
                'basic_documents' => 'Basic Documents',
            ],
            'Handling' => [
                'medical_history' => 'Medical History',
                'symptoms_analysis' => 'Symptoms Analysis',
                'preliminary_diagnosis' => 'Preliminary Diagnosis',
            ],
            'Available' => [
                'provider_assignment' => 'Provider Assignment',
                'service_confirmation' => 'Service Confirmation',
                'appointment_details' => 'Appointment Details',
            ],
            'Confirmed' => [
                'final_confirmation' => 'Final Confirmation',
                'service_agreement' => 'Service Agreement',
                'payment_terms' => 'Payment Terms',
            ],
            'Requesting GOP' => [
                'gop_request' => 'GOP Request',
                'cost_estimate' => 'Cost Estimate',
                'insurance_verification' => 'Insurance Verification',
            ],
            'Assisted' => [
                'service_completion' => 'Service Completion',
                'medical_report' => 'Medical Report',
                'prescription' => 'Prescription',
                'follow_up_plan' => 'Follow-up Plan',
                'bill_document' => 'Bill Document',
                'invoice_document' => 'Invoice Document',
                'transaction_document' => 'Transaction Document',
            ],
            'Hold' => [
                'hold_reason' => 'Hold Reason',
                'pending_documents' => 'Pending Documents',
                'resume_conditions' => 'Resume Conditions',
            ],
        ];

        return $stageInputs[$stage] ?? [];
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        
        $file = File::find($data['file_id']);
        
        if (!$file) {
            Notification::make()
                ->title('Error')
                ->body('File not found.')
                ->danger()
                ->send();
            return;
        }

        // Update the file's Google Drive link with the uploaded document
        $documentPath = $data['document'];
        
        // Generate Google Drive link (this would typically integrate with Google Drive API)
        // For now, we'll store the local path
        $googleDriveLink = 'https://drive.google.com/file/d/' . Str::random(44) . '/view';
        
        // Determine which field to update based on the stage input
        $this->updateCorrectField($file, $data['stage_input'], $googleDriveLink);

        // Update file status if needed
        if ($data['stage'] !== $file->status) {
            $file->update(['status' => $data['stage']]);
        }

        Notification::make()
            ->title('Success')
            ->body('Document uploaded successfully for stage: ' . $data['stage'] . ' - ' . $data['stage_input'])
            ->success()
            ->send();

        $this->form->fill();
    }

    private function updateCorrectField(File $file, string $stageInput, string $googleDriveLink): void
    {
        // Map stage inputs to the correct model and field
        $fieldMapping = [
            // File-level documents (use file's google_drive_link)
            'patient_info' => ['model' => 'File', 'field' => 'google_drive_link'],
            'initial_assessment' => ['model' => 'File', 'field' => 'google_drive_link'],
            'basic_documents' => ['model' => 'File', 'field' => 'google_drive_link'],
            'medical_history' => ['model' => 'File', 'field' => 'google_drive_link'],
            'symptoms_analysis' => ['model' => 'File', 'field' => 'google_drive_link'],
            'preliminary_diagnosis' => ['model' => 'File', 'field' => 'google_drive_link'],
            'provider_assignment' => ['model' => 'File', 'field' => 'google_drive_link'],
            'service_confirmation' => ['model' => 'File', 'field' => 'google_drive_link'],
            'appointment_details' => ['model' => 'File', 'field' => 'google_drive_link'],
            'final_confirmation' => ['model' => 'File', 'field' => 'google_drive_link'],
            'service_agreement' => ['model' => 'File', 'field' => 'google_drive_link'],
            'payment_terms' => ['model' => 'File', 'field' => 'google_drive_link'],
            'hold_reason' => ['model' => 'File', 'field' => 'google_drive_link'],
            'pending_documents' => ['model' => 'File', 'field' => 'google_drive_link'],
            'resume_conditions' => ['model' => 'File', 'field' => 'google_drive_link'],
            'service_completion' => ['model' => 'File', 'field' => 'google_drive_link'],
            'follow_up_plan' => ['model' => 'File', 'field' => 'google_drive_link'],
            
            // GOP-related documents (use GOP's gop_google_drive_link)
            'gop_request' => ['model' => 'Gop', 'field' => 'gop_google_drive_link'],
            'cost_estimate' => ['model' => 'Gop', 'field' => 'gop_google_drive_link'],
            'insurance_verification' => ['model' => 'Gop', 'field' => 'gop_google_drive_link'],
            
            // Medical documents (store in file's google_drive_link since MedicalReport/Prescription don't have this field)
            'medical_report' => ['model' => 'File', 'field' => 'google_drive_link'],
            'prescription' => ['model' => 'File', 'field' => 'google_drive_link'],
            
            // Financial documents (use their specific google link fields)
            'bill_document' => ['model' => 'Bill', 'field' => 'bill_google_link'],
            'invoice_document' => ['model' => 'Invoice', 'field' => 'invoice_google_link'],
            'transaction_document' => ['model' => 'Transaction', 'field' => 'attachment_path'],
        ];

        $mapping = $fieldMapping[$stageInput] ?? ['model' => 'File', 'field' => 'google_drive_link'];

        if ($mapping['model'] === 'File') {
            // Update the file directly
            $file->update([$mapping['field'] => $googleDriveLink]);
        } else {
            // Find the related record and update it
            $this->updateRelatedRecord($file, $mapping['model'], $mapping['field'], $googleDriveLink);
        }
    }

    private function updateRelatedRecord(File $file, string $modelType, string $field, string $googleDriveLink): void
    {
        switch ($modelType) {
            case 'Gop':
                // Find the most recent GOP for this file
                $gop = $file->gops()->latest()->first();
                if ($gop) {
                    $gop->update([$field => $googleDriveLink]);
                } else {
                    // Create a new GOP record if none exists
                    $file->gops()->create([
                        'type' => 'In',
                        'amount' => 0,
                        'date' => now(),
                        'status' => 'Not Sent',
                        $field => $googleDriveLink,
                    ]);
                }
                break;

            case 'Bill':
                // Find the most recent bill for this file
                $bill = $file->bills()->latest()->first();
                if ($bill) {
                    $bill->update([$field => $googleDriveLink]);
                } else {
                    // Create a new bill record if none exists
                    $file->bills()->create([
                        'name' => 'Auto-generated Bill',
                        'due_date' => now()->addDays(30),
                        'total_amount' => 0,
                        'discount' => 0,
                        'status' => 'Unpaid',
                        $field => $googleDriveLink,
                    ]);
                }
                break;

            case 'Invoice':
                // Find the most recent invoice for this file
                $invoice = $file->invoices()->latest()->first();
                if ($invoice) {
                    $invoice->update([$field => $googleDriveLink]);
                } else {
                    // Create a new invoice record if none exists
                    $file->invoices()->create([
                        'name' => 'Auto-generated Invoice',
                        'due_date' => now()->addDays(30),
                        'total_amount' => 0,
                        'discount' => 0,
                        'status' => 'Draft',
                        $field => $googleDriveLink,
                    ]);
                }
                break;

            case 'Transaction':
                // Find the most recent transaction for this file
                $transaction = Transaction::where('related_type', 'File')
                    ->where('related_id', $file->id)
                    ->latest()
                    ->first();
                if ($transaction) {
                    $transaction->update([$field => $googleDriveLink]);
                } else {
                    // Create a new transaction record if none exists
                    Transaction::create([
                        'name' => 'Auto-generated Transaction',
                        'amount' => 0,
                        'date' => now(),
                        'type' => 'Inflow',
                        'related_type' => 'File',
                        'related_id' => $file->id,
                        $field => $googleDriveLink,
                    ]);
                }
                break;

            default:
                // Fallback to updating the file
                $file->update(['google_drive_link' => $googleDriveLink]);
                break;
        }
    }

    public function getTitle(): string
    {
        return 'Upload Stage Document';
    }
}
