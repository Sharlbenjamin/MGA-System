<?php

namespace App\Filament\Resources\FileResource\Pages;

use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Filament\Infolists\Infolist;
use App\Filament\Resources\FileResource;
use Filament\Infolists\Components\Card;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Actions\Action as InfolistAction;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Filament\Widgets\CommentsWidget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Models\DraftMail;
use Filament\Forms\Components\RichEditor;
use App\Models\Task;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\FileFee;
use App\Filament\Resources\BranchAvailabilityResource;

use Filament\Support\Colors\Color;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Support\Facades\Storage;
use App\Services\DocumentPathResolver;
use Filament\Infolists\Components\ViewEntry;

class ViewFile extends ViewRecord
{
    protected static string $resource = FileResource::class;

    public function getTitle(): string
    {
        return $this->record->mga_reference . ' · ' . ($this->record->status ?? '');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $overviewSchema = [
            ViewEntry::make('compact_content')
                ->getStateUsing(fn ($record) => $record)
                ->view('filament.pages.files.view-file-compact')
                ->columnSpanFull()
                ->extraAttributes(['class' => '!max-w-none w-full']),
        ];

        return $infolist
            ->columns(1)
            ->schema([
                Tabs::make('FileTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Overview')
                            ->schema($overviewSchema),
                        Tab::make('Documents')
                            ->schema($this->getDocumentsTabContent()),
                    ]),
            ]);
    }

    protected function getDocumentsTabContent(): array
    {
        $record = $this->record;
        
        return [
            InfolistSection::make('Document Management')
                ->description('Upload and manage documents for this file')
                ->schema([
                    InfolistSection::make('GOP Documents')
                        ->schema([
                            Actions::make([
                                InfolistAction::make('upload_gop')
                                    ->label('Upload GOP Document')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select GOP Document')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'gops'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updateGopDocumentPath($this->record, $data['file']);
                                            Notification::make()
                                                ->success()
                                                ->title('GOP Document uploaded successfully')
                                                ->send();
                                        }
                                    }),
                            ]),
                            $this->getDocumentTable('gops', 'GOP Documents'),
                        ]),
                    
                    InfolistSection::make('Medical Reports')
                        ->schema([
                            Actions::make([
                                InfolistAction::make('upload_medical_report')
                                    ->label('Upload Medical Report')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select Medical Report')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'medical_reports'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updateMedicalReportDocumentPath($this->record, $data['file']);
                                            Notification::make()
                                                ->success()
                                                ->title('Medical Report uploaded successfully')
                                                ->send();
                                        }
                                    }),
                            ]),
                            $this->getDocumentTable('medical_reports', 'Medical Reports'),
                        ]),
                    
                    InfolistSection::make('Prescriptions')
                        ->schema([
                            Actions::make([
                                InfolistAction::make('upload_prescription')
                                    ->label('Upload Prescription')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select Prescription')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'prescriptions'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updatePrescriptionDocumentPath($this->record, $data['file']);
                                            Notification::make()
                                                ->success()
                                                ->title('Prescription uploaded successfully')
                                                ->send();
                                        }
                                    }),
                            ]),
                            $this->getDocumentTable('prescriptions', 'Prescriptions'),
                        ]),
                    
                    InfolistSection::make('Bills')
                        ->schema([
                            Actions::make([
                                InfolistAction::make('upload_bill')
                                    ->label('Upload Bill')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select Bill')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'bills'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updateBillDocumentPath($this->record, $data['file']);
                                            Notification::make()
                                                ->success()
                                                ->title('Bill uploaded successfully')
                                                ->send();
                                        }
                                    }),
                            ]),
                            $this->getDocumentTable('bills', 'Bills'),
                        ]),
                    
                    InfolistSection::make('Invoices')
                        ->schema([
                            Actions::make([
                                InfolistAction::make('upload_invoice')
                                    ->label('Upload Invoice')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select Invoice')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'invoices'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updateInvoiceDocumentPath($this->record, $data['file']);
                                            Notification::make()
                                                ->success()
                                                ->title('Invoice uploaded successfully')
                                                ->send();
                                        }
                                    }),
                            ]),
                            $this->getDocumentTable('invoices', 'Invoices'),
                        ]),
                    
                    InfolistSection::make('Transactions')
                        ->schema([
                            Actions::make([
                                InfolistAction::make('upload_transaction_in')
                                    ->label('Upload Transaction In Document')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select Transaction In Document')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'transactions/in'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updateTransactionDocumentPath($this->record, $data['file'], 'in');
                                            Notification::make()
                                                ->success()
                                                ->title('Transaction In document uploaded successfully')
                                                ->send();
                                        }
                                    }),
                                InfolistAction::make('upload_transaction_out')
                                    ->label('Upload Transaction Out Document')
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('primary')
                                    ->form([
                                        \Filament\Forms\Components\FileUpload::make('file')
                                            ->label('Select Transaction Out Document')
                                            ->directory(fn () => app(DocumentPathResolver::class)->dirFor($this->record, 'transactions/out'))
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->maxFiles(1)
                                            ->required(),
                                    ])
                                    ->action(function (array $data) {
                                        if (isset($data['file'])) {
                                            $this->updateTransactionDocumentPath($this->record, $data['file'], 'out');
                                            Notification::make()
                                                ->success()
                                                ->title('Transaction Out document uploaded successfully')
                                                ->send();
                                        }
                                    }),
                            ]),
                            $this->getDocumentTable('transactions/in', 'Transactions (In)'),
                            $this->getDocumentTable('transactions/out', 'Transactions (Out)'),
                        ]),
                ]),
        ];
    }

    protected function getDocumentTable(string $category, string $title): \Filament\Infolists\Components\Section
    {
        $record = $this->record;
        $resolver = app(DocumentPathResolver::class);
        $directory = $resolver->dirFor($record, $category);
        
        // Get files from the directory
        $files = [];
        if (Storage::disk('public')->exists($directory)) {
            $filePaths = Storage::disk('public')->files($directory);
            foreach ($filePaths as $filePath) {
                $files[] = [
                    'name' => basename($filePath),
                    'path' => $filePath,
                    'size' => Storage::disk('public')->size($filePath),
                    'created_at' => Storage::disk('public')->lastModified($filePath),
                ];
            }
        }
        
        $components = [];
        
        if (empty($files)) {
            $components[] = TextEntry::make('no_files')
                ->label('')
                ->state('No files found')
                ->color('gray');
        } else {
            foreach ($files as $index => $file) {
                $components[] = InfolistSection::make("file_{$index}")
                    ->schema([
                        TextEntry::make('name')
                            ->label('Filename')
                            ->state($file['name']),
                        TextEntry::make('size')
                            ->label('Size')
                            ->state($this->formatFileSize($file['size'])),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->state(date('Y-m-d H:i:s', $file['created_at'])),
                        TextEntry::make('actions')
                            ->label('Actions')
                            ->state(function () use ($file) {
                                $previewUrl = asset('storage/' . $file['path']);
                                $downloadUrl = route('docs.serve', ['type' => 'file', 'id' => $this->record->id]);
                                
                                return "
                                    <a href='{$previewUrl}' target='_blank' class='text-blue-600 hover:text-blue-800'>Preview</a> | 
                                    <a href='{$downloadUrl}' class='text-green-600 hover:text-green-800'>Download</a> | 
                                    <button onclick='deleteFile(\"{$file['path']}\")' class='text-red-600 hover:text-red-800'>Delete</button>
                                ";
                            })
                            ->html(),
                    ])
                    ->columns(2);
            }
        }
        
        return InfolistSection::make($title)
            ->schema($components);
    }

    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function updateGopDocumentPath($record, $filePath): void
    {
        $gop = $record->gops()->latest()->first();
        if ($gop) {
            $gop->update(['document_path' => $filePath]);
        } else {
            $record->gops()->create([
                'type' => 'In',
                'amount' => 0,
                'date' => now(),
                'status' => 'Not Sent',
                'document_path' => $filePath,
            ]);
        }
    }

    protected function updateMedicalReportDocumentPath($record, $filePath): void
    {
        $medicalReport = $record->medicalReports()->latest()->first();
        if ($medicalReport) {
            $medicalReport->update(['document_path' => $filePath]);
        } else {
            $record->medicalReports()->create([
                'date' => now(),
                'status' => 'Received',
                'document_path' => $filePath,
            ]);
        }
    }

    protected function updatePrescriptionDocumentPath($record, $filePath): void
    {
        $prescription = $record->prescriptions()->latest()->first();
        if ($prescription) {
            $prescription->update(['document_path' => $filePath]);
        } else {
            $record->prescriptions()->create([
                'name' => 'Uploaded Prescription',
                'serial' => 'UPL-' . now()->format('YmdHis'),
                'date' => now(),
                'document_path' => $filePath,
            ]);
        }
    }

    protected function updateBillDocumentPath($record, $filePath): void
    {
        $bill = $record->bills()->latest()->first();
        if ($bill) {
            $bill->update(['bill_document_path' => $filePath]);
        } else {
            $record->bills()->create([
                'name' => 'Uploaded Bill',
                'due_date' => now()->addDays(14),
                'total_amount' => 0,
                'discount' => 0,
                'status' => 'Unpaid',
                'bill_document_path' => $filePath,
            ]);
        }
    }

    protected function updateInvoiceDocumentPath($record, $filePath): void
    {
        $invoice = $record->invoices()->latest()->first();
        if ($invoice) {
            $invoice->update(['invoice_document_path' => $filePath]);
        } else {
            $invoice = $record->invoices()->create([
                'name' => 'Uploaded Invoice',
                'due_date' => now()->addDays(30),
                'total_amount' => 0,
                'discount' => 0,
                'status' => 'Draft',
                'invoice_document_path' => $filePath,
            ]);
        }
    }

    protected function updateTransactionDocumentPath($record, $filePath, string $type): void
    {
        // For transactions, we'll store the path in a general way
        // since transactions don't have a direct file relationship
        $transaction = \App\Models\Transaction::where('related_type', 'File')
            ->where('related_id', $record->id)
            ->where('type', ucfirst($type))
            ->latest()
            ->first();
            
        if ($transaction) {
            $transaction->update(['attachment_path' => $filePath]);
        } else {
            \App\Models\Transaction::create([
                'name' => "Uploaded Transaction ({$type})",
                'amount' => 0,
                'date' => now(),
                'type' => ucfirst($type),
                'related_type' => 'File',
                'related_id' => $record->id,
                'attachment_path' => $filePath,
            ]);
        }
    }


    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('requestAppointment')
                ->label('Request Appointment')
                ->icon('heroicon-o-globe-alt')
                ->color('primary')
                ->slideOver()
                ->modalHeading('Request Appointment - Select Provider Branches')
                ->modalDescription('Choose which provider branches to send appointment requests to. Branches are filtered by city, service type, and active status, sorted by distance.')
                ->modalWidth('7xl')
                ->form([
                    Section::make('Filters')
                        ->description('Filter providers by city (defaults to file\'s city)')
                        ->schema([
                            Select::make('city_filter')
                                ->label('Filter by City')
                                ->options(function () {
                                    // Get cities from the same country as the file
                                    $countryId = $this->record->country_id ?? null;
                                    if (!$countryId) {
                                        return \App\Models\City::orderBy('name')->pluck('name', 'id');
                                    }
                                    return \App\Models\City::where('country_id', $countryId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('Use file\'s city')
                                ->default(fn () => $this->record->city_id ?? null)
                                ->live()
                                ->afterStateUpdated(function ($state, $set) {
                                    // Clear selected branches when city filter changes
                                    $set('selected_branches', []);
                                    $set('select_all_branches', false);
                                }),
                        ])
                        ->collapsible()
                        ->collapsed(false),
                    
                    Section::make('Available Branches')
                        ->description('Select the provider branches you want to send appointment requests to')
                        ->key(fn ($get) => 'branches-' . ($get('city_filter') ?? 'default'))
                        ->schema([
                            // Table-like header
                            Grid::make(12)
                                ->schema([
                                    Checkbox::make('select_all_branches')
                                        ->label('')
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            $cityFilter = $get('city_filter');
                                            $branches = $this->getDisplayedProviderBranchesForRequest($cityFilter);
                                            $branchIds = $branches->pluck('id')->toArray();
                                            
                                            if ($state) {
                                                $set('selected_branches', $branchIds);
                                                // Check all individual checkboxes
                                                foreach ($branchIds as $branchId) {
                                                    $set("branch_{$branchId}", true);
                                                }
                                            } else {
                                                $set('selected_branches', []);
                                                // Uncheck all individual checkboxes
                                                foreach ($branchIds as $branchId) {
                                                    $set("branch_{$branchId}", false);
                                                }
                                            }
                                        })
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_branch')
                                        ->label('Branch Name')
                                        ->content('')
                                        ->columnSpan(2),
                                    \Filament\Forms\Components\Placeholder::make('header_priority')
                                        ->label('Priority')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_cost')
                                        ->label('Cost')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_communication')
                                        ->label('Contact By')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_contact')
                                        ->label('Contact')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_phone')
                                        ->label('Phone')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_address')
                                        ->label('Address')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_website')
                                        ->label('Website')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_distance')
                                        ->label('Distance')
                                        ->content('')
                                        ->columnSpan(1),
                                    \Filament\Forms\Components\Placeholder::make('header_request')
                                        ->label('Request')
                                        ->content('')
                                        ->columnSpan(1),
                                ])
                                ->extraAttributes(['class' => 'bg-gray-50 border-b-2 border-gray-200 font-semibold text-sm']),
                            
                            // Branch rows - reactive to country filter
                            // The key() on the section will force re-render when country filter changes
                            ...$this->getBranchRows(),
                            
                            // Hidden field to capture selected branches
                            \Filament\Forms\Components\Hidden::make('selected_branches')
                                ->default([])
                                ->rules(['array'])
                                ->validationMessages([
                                    'array' => 'Selected branches must be an array.',
                                ]),
                        ])
                        ->collapsible(),
                    
                    Section::make('Additional Email Recipients')
                        ->description('Add any additional email addresses to receive the appointment request')
                        ->schema([
                            Repeater::make('custom_emails')
                                ->label('Additional Email Recipients')
                                ->schema([
                                    TextInput::make('email')
                                        ->label('Email Address')
                                        ->email()
                                        ->required()
                                        ->placeholder('example@email.com'),
                                ])
                                ->addActionLabel('Add Email')
                                ->reorderable(false)
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['email'] ?? null)
                                ->defaultItems(0),
                        ])
                        ->collapsible(),
                ])
                ->action(function (array $data, $record) {
                    $this->sendAppointmentRequestsFromModal($data, $record);
                })
                ->modalSubmitActionLabel('Send Appointment Requests')
                ->modalCancelActionLabel('Cancel'),
            Action::make('exportMedicalReport')
                ->label('Export MR')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->visible(fn ($record) => $record->medicalReports()->exists())
                ->action(function ($record) {
                    $medicalReport = $record->medicalReports()->latest()->first();
                    if (!$medicalReport) {
                        return;
                    }
                    
                    $pdf = Pdf::loadView('pdf.medicalReport', ['medicalReport' => $medicalReport]);
                    $fileName = 'Medical_Report_' . $record->patient->name . '_' . ($medicalReport->date?->format('Y-m-d') ?? now()->format('Y-m-d')) . '.pdf';
                    
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $fileName
                    );
                }),
            Action::make('exportPrescription')
                ->label('Export PRX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn ($record) => $record->prescriptions()->exists())
                ->action(function ($record) {
                    $prescription = $record->prescriptions()->latest()->first();
                    if (!$prescription) {
                        return;
                    }
                    
                    $pdf = Pdf::loadView('pdf.prescription', ['prescription' => $prescription]);
                    $fileName = $prescription->file->patient->name . ' Prescription Report ' . $prescription->file->mga_reference . '.pdf';
                    
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $fileName
                    );
                }),
            Action::make('extractConsent')
                ->label('Extract consent')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->action(function ($record) {
                    $pdf = Pdf::loadView('pdf.consent', ['file' => $record]);
                    $fileName = 'Consent_Form_' . ($record->patient->name ?? 'Patient') . '_' . $record->mga_reference . '.pdf';
                    
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $fileName
                    );
                }),
            Action::make('notifyClient')
                ->label('Ready Replies')
                ->slideOver()
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->modalHeading('Ready Replies')
                ->modalWidth('7xl')
                ->form([
                    Select::make('category')
                        ->label('Category')
                        ->options([
                            'Provider' => 'Provider',
                            'Patient' => 'Patient',
                            'Client' => 'Client',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $record, $get) {
                            $this->updateReadyReplyMessage($set, $record, $get);
                        }),
                    
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'New' => 'New',
                            'Available' => 'Available',
                            'Confirmed' => 'Confirmed',
                            'Assisted' => 'Assisted',
                            'Waiting MR' => 'Waiting MR',
                            'Refund' => 'Refund',
                            'Cancelled' => 'Cancelled',
                            'Void' => 'Void',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $record, $get) {
                            $this->updateReadyReplyMessage($set, $record, $get);
                        }),
                    
                    Textarea::make('message_display')
                        ->label('Message')
                        ->rows(10)
                        ->disabled()
                        ->placeholder('Select Category and Status to see the message...')
                        ->columnSpanFull(),
                    
                ])
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    \Filament\Actions\Action::make('copy_message')
                        ->label('Copy Message')
                        ->icon('heroicon-o-clipboard-document')
                        ->color(Color::Gray)
                        ->action(function ($record, $get) {
                            $category = $get('category');
                            $status = $get('status');
                            
                            if (!$category || !$status) {
                                Notification::make()
                                    ->title('No message to copy')
                                    ->body('Please select Category and Status first.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            $readyReplies = $this->getReadyReplies();
                            
                            if (!isset($readyReplies[$category][$status])) {
                                Notification::make()
                                    ->title('Message not found')
                                    ->body('Message not found for this combination.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            $message = $readyReplies[$category][$status];
                            $processedMessage = $this->processReadyReplyPlaceholders($message, $record);
                            $this->copyToClipboard($processedMessage, 'Ready Reply Message');
                        }),
                ]),
            Action::make('viewFinancial')
                ->label('Invocies & Bills')
                ->icon('heroicon-o-document-currency-euro')
                ->url(fn ($record) => route('filament.admin.resources.patients.financial', [
                    'record' => $record->patient_id,
                    'file_id' => $record->id
                ]))
                ->openUrlInNewTab(false)->color('success'),


            Action::make('confirmTelemedicine')
                ->label('Confirm Telemedicine')
                ->icon('heroicon-o-video-camera')
                ->color('success')
                ->visible(fn ($record) => $record->service_type_id === 2 && $record->appointments()->where('status', 'Requested')->exists())
                ->requiresConfirmation()
                ->modalHeading('Confirm Telemedicine Appointment')
                ->modalDescription('This will confirm the latest requested appointment for this telemedicine file and update all related fields.')
                ->modalSubmitActionLabel('Confirm Appointment')
                ->action(function ($record) {
                    try {
                        $appointment = $record->confirmTelemedicineAppointment();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Telemedicine Appointment Confirmed')
                            ->body('The appointment has been confirmed successfully. Google Meet link has been generated.')
                            ->success()
                            ->send();
                            
                        return redirect()->to(route('filament.admin.resources.files.view', $record));
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('addComment')
                ->label('Add Comment')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('gray')
                ->slideOver()
                ->modalHeading('Add a Comment')
                ->form([
                    Textarea::make('content')
                        ->label('Comment')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->comments()->create([
                        'content' => $data['content'],
                        'user_id' => Auth::id(),
                    ]);
                    Notification::make()->success()->title('Comment added')->send();
                }),
            Action::make('assignEmployee')
                ->label('Assign Employee')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->modalHeading('Assign to employee')
                ->form([
                    Select::make('user_id')
                        ->label('Employee / User')
                        ->options(\App\Models\User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = \App\Models\User::find($data['user_id']);
                    if (!$user) {
                        Notification::make()->danger()->title('User not found')->send();
                        return;
                    }
                    app(\App\Services\CaseAssignmentService::class)->assign(
                        $this->record,
                        $user,
                        auth()->user()
                    );
                    Notification::make()->success()->title('Case assigned')->body("Assigned to {$user->name}.")->send();
                }),
            Action::make('Update File')
                ->label('Update File')
                ->icon('heroicon-o-pencil')
                ->url(fn ($record) => route('filament.admin.resources.files.edit', $record))
                ->openUrlInNewTab(false)
        ];
        return [
            ActionGroup::make($actions)
                ->icon('heroicon-m-ellipsis-vertical')
                ->label('Actions')
                ->tooltip('Actions'),
            Action::make('editTask')
                ->label('Edit Task')
                ->hidden()
                ->modalHeading('Edit Task')
                ->modalWidth('md')
                ->form([
                    \Filament\Forms\Components\Hidden::make('task_id'),
                    TextInput::make('title')
                        ->label('Task')
                        ->required(),
                    Select::make('user_id')
                        ->label('Assigned employee')
                        ->options(\App\Models\User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('is_done')
                        ->label('Status')
                        ->options([
                            0 => 'Pending',
                            1 => 'Done',
                        ])
                        ->required(),
                    \Filament\Forms\Components\Placeholder::make('linked_case')
                        ->label('Linked case')
                        ->content(fn ($get) => $this->record?->mga_reference ?? '—'),
                    Textarea::make('description')
                        ->label('Task comment')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $taskId = $data['task_id'] ?? null;
                    if (!$taskId) {
                        Notification::make()->danger()->title('Task not found')->send();
                        return;
                    }
                    $task = Task::find($taskId);
                    if (!$task) {
                        Notification::make()->danger()->title('Task not found')->send();
                        return;
                    }
                    $task->update([
                        'title' => $data['title'],
                        'user_id' => $data['user_id'],
                        'is_done' => (bool) $data['is_done'],
                        'description' => $data['description'] ?? null,
                    ]);
                    Notification::make()->success()->title('Task updated')->send();
                }),
        ];
    }


    public function openEditTaskModal(int $taskId): void
    {
        $task = Task::find($taskId);
        if (!$task) {
            Notification::make()->danger()->title('Task not found')->send();
            return;
        }
        $this->mountAction('editTask', [
            'task_id' => $task->id,
            'title' => $task->title,
            'user_id' => $task->user_id,
            'is_done' => $task->is_done ? 1 : 0,
            'description' => $task->description ?? '',
        ]);
    }

    public function mount($record): void
    {
        parent::mount($record);

        $this->alertMessage = Session::get('contact_alert');
    }

    public $alertMessage;

    public function clearAlert(): void
    {
        $this->alertMessage = null;
        Session::forget('contact_alert');
    }

    /** Used by compact view "Copy for email/WhatsApp" button. */
    public function copySummaryToClipboard(): void
    {
        $this->copyToClipboard($this->formatCaseInfo($this->record), 'Case Summary');
    }

    public function copyToClipboard($text, $label): void
    {
        // Show success notification
        Notification::make()
            ->title("Copied to clipboard")
            ->body("'{$label}' has been copied to your clipboard")
            ->success()
            ->send();
            
        // Properly escape the text for JavaScript, preserving newlines
        $escapedText = json_encode($text, JSON_HEX_APOS | JSON_HEX_QUOT);
        
        // Return JavaScript to copy to clipboard
        $this->js("
            (function() {
                console.log('=== COPY TO CLIPBOARD DEBUG ===');
                console.log('Text to copy:', " . $escapedText . ");
                console.log('User agent:', navigator.userAgent);
                console.log('Is secure context:', window.isSecureContext);
                console.log('Clipboard API available:', !!navigator.clipboard);
                
                var textToCopy = " . $escapedText . ";
                
                // Try modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    console.log('Trying modern clipboard API...');
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        console.log('✅ Text copied successfully (modern API)');
                    }).catch(function(err) {
                        console.error('❌ Modern clipboard API failed:', err);
                        fallbackCopy();
                    });
                } else {
                    console.log('Modern clipboard API not available, using fallback...');
                    fallbackCopy();
                }
                
                function fallbackCopy() {
                    console.log('Trying input fallback...');
                    
                    // Create a temporary input element (works better on iOS than textarea)
                    var input = document.createElement('input');
                    input.type = 'text';
                    input.value = textToCopy;
                    input.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 1px; height: 1px; opacity: 0.01; z-index: 9999; background: transparent; border: none; outline: none;';
                    
                    document.body.appendChild(input);
                    console.log('Input element added to DOM');
                    
                    // For iOS, we need to focus and select
                    input.focus();
                    input.select();
                    input.setSelectionRange(0, input.value.length);
                    console.log('Input focused and selected');
                    
                    try {
                        var successful = document.execCommand('copy');
                        console.log('execCommand result:', successful);
                        if (successful) {
                            console.log('✅ Text copied successfully (input fallback)');
                        } else {
                            console.error('❌ execCommand copy failed');
                            // Try with textarea as last resort
                            textareaFallback();
                        }
                    } catch (err) {
                        console.error('❌ execCommand copy error:', err);
                        // Try with textarea as last resort
                        textareaFallback();
                    }
                    
                    // Remove the input after a short delay
                    setTimeout(function() {
                        if (document.body.contains(input)) {
                            document.body.removeChild(input);
                            console.log('Input element removed');
                        }
                    }, 100);
                }
                
                function textareaFallback() {
                    console.log('Trying textarea fallback...');
                    
                    // Last resort: use textarea with iOS-specific handling
                    var textArea = document.createElement('textarea');
                    textArea.value = textToCopy;
                    textArea.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 2px; height: 2px; opacity: 0.01; z-index: 9999; background: white; border: 1px solid #ccc;';
                    
                    document.body.appendChild(textArea);
                    console.log('Textarea element added to DOM');
                    
                    // iOS-specific: focus, select, and try to copy
                    textArea.focus();
                    textArea.select();
                    console.log('Textarea focused and selected');
                    
                    // Try multiple times for iOS
                    var attempts = 0;
                    var maxAttempts = 3;
                    
                    function tryCopy() {
                        attempts++;
                        console.log('Copy attempt ' + attempts + ' of ' + maxAttempts);
                        
                        try {
                            var successful = document.execCommand('copy');
                            console.log('execCommand result (attempt ' + attempts + '):', successful);
                            
                            if (successful) {
                                console.log('✅ Text copied successfully (textarea fallback, attempt ' + attempts + ')');
                                document.body.removeChild(textArea);
                                return;
                            } else if (attempts < maxAttempts) {
                                console.log('Retrying in 50ms...');
                                // Try again after a short delay
                                setTimeout(tryCopy, 50);
                            } else {
                                console.error('❌ All copy attempts failed');
                                document.body.removeChild(textArea);
                            }
                        } catch (err) {
                            console.error('❌ Copy attempt ' + attempts + ' failed:', err);
                            if (attempts < maxAttempts) {
                                console.log('Retrying in 50ms...');
                                setTimeout(tryCopy, 50);
                            } else {
                                document.body.removeChild(textArea);
                            }
                        }
                    }
                    
                    tryCopy();
                }
            })();
        ");
    }

    /**
     * Get the 6 compact-view tasks (Create GOP In, Upload GOP In, Create MR, Upload MR, Create Bill, Upload Bill).
     * Returns [ ['name' => string, 'status' => string, 'assignee' => string], ... ].
     */
    public function getCompactTasksForView($record): array
    {
        $titles = [
            'Create GOP In',
            'Upload GOP In',
            'Create MR',
            'Upload MR',
            'Create Bill',
            'Upload Bill',
        ];
        $fileTasks = $record->tasks()->where('department', 'Operation')->get()->keyBy(function (Task $t) {
            return $t->title;
        });
        $result = [];
        foreach ($titles as $title) {
            $task = $fileTasks->get($title) ?? $fileTasks->first(fn (Task $t) => stripos($t->title, $title) !== false || stripos($title, $t->title) !== false);
            $result[] = [
                'name' => $title,
                'status' => $task ? ($task->is_done ? 'Done' : 'Pending') : 'Pending',
                'assignee' => $task && $task->user ? $task->user->name : '—',
            ];
        }
        return $result;
    }

    public function formatCaseInfo($record): string
    {
        // Helper function to check if a value is effectively empty
        $isEmpty = function($value) {
            return $value === null || $value === '' || (is_string($value) && trim($value) === '');
        };
        
        // Get patient name with fallback
        $patientName = 'N/A';
        if ($record->patient && !$isEmpty($record->patient->name)) {
            $patientName = trim($record->patient->name);
        }
        
        // Get DOB with fallback
        $dob = 'N/A';
        if ($record->patient && !$isEmpty($record->patient->dob)) {
            try {
                $dob = \Carbon\Carbon::parse($record->patient->dob)->format('d/m/Y');
            } catch (\Exception $e) {
                $dob = 'N/A';
            }
        }
        
        // Get MGA Reference with fallback
        $mgaReference = 'N/A';
        if (!$isEmpty($record->mga_reference)) {
            $mgaReference = trim($record->mga_reference);
        }
        
        // Get symptoms with fallback
        $symptoms = 'N/A';
        if (!$isEmpty($record->symptoms)) {
            $symptoms = trim($record->symptoms);
        }
        
        // Get service type with fallback
        $serviceType = 'N/A';
        if ($record->serviceType && !$isEmpty($record->serviceType->name)) {
            $serviceType = trim($record->serviceType->name);
        }
        
        // Format service date and time with fallbacks
        $serviceDate = 'N/A';
        $serviceTime = 'N/A';
        
        // Check service date - handle Carbon date casting
        if ($record->service_date) {
            try {
                // service_date is cast as 'date' so it's already a Carbon instance
                $serviceDate = $record->service_date->format('d/m/Y');
            } catch (\Exception $e) {
                $serviceDate = 'N/A';
            }
        }
        
        // Check service time - handle string time
        if (!$isEmpty($record->service_time)) {
            try {
                // service_time is stored as string, parse it to format properly
                $serviceTime = \Carbon\Carbon::parse($record->service_time)->format('h:iA');
            } catch (\Exception $e) {
                $serviceTime = 'N/A';
            }
        }
        
        $request = "{$serviceType} on {$serviceDate} at {$serviceTime}";

        // Get phone with fallback
        $phone = 'N/A';
        if (!$isEmpty($record->phone)) {
            $phone = trim($record->phone);
        }

        // Get address with fallback
        $address = 'N/A';
        if (!$isEmpty($record->address)) {
            $address = trim($record->address);
        }

        // Return formatted string with proper line breaks
        return "Patient Name: {$patientName}\nDOB: {$dob}\nMGA Reference: {$mgaReference}\nSymptoms: {$symptoms}\nRequest: {$request}\nPhone: {$phone}\nAddress: {$address}";
    }

    /**
     * Update the preview content based on current form state
     */
    public function updatePreview($set, $record, $get): void
    {
        $draftMailId = $get('draft_mail_id');
        if ($draftMailId) {
            $draftMail = DraftMail::find($draftMailId);
            if ($draftMail) {
                $includeFields = $get('include_fields') ?? [];
                $customNotes = $get('custom_notes') ?? '';
                $processedMessage = $this->processTemplate($draftMail->body_mail, $record, $includeFields, $customNotes);
                $set('preview_content', $processedMessage);
                
                // Debug: Log the update
                Log::info('Preview updated', [
                    'draft_mail_id' => $draftMailId,
                    'include_fields' => $includeFields,
                    'custom_notes' => $customNotes,
                    'message_length' => strlen($processedMessage)
                ]);
            }
        } else {
            // If no template selected, clear the preview
            $set('preview_content', '');
        }
    }

    /**
     * Process template with file data and optional fields
     */
    public function processTemplate(string $template, $record, array $includeFields = [], string $customNotes = ''): string
    {
        $data = $this->getFileData($record, $includeFields);
        
        // Replace placeholders in template
        $message = $template;
        
        // Handle conditional blocks first
        $message = $this->processConditionalBlocks($message, $data);
        
        // Replace simple placeholders
        foreach ($data as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        
        // Add custom notes if provided
        if (!empty($customNotes)) {
            $message .= "\n\n" . trim($customNotes);
        }
        
        return $message;
    }

    /**
     * Process conditional blocks in templates
     */
    private function processConditionalBlocks(string $message, array $data): string
    {
        // Handle {{#if field}}content{{/if}} blocks
        $pattern = '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s';
        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $field = $matches[1];
            $content = $matches[2];
            
            // Check if the field exists and has a value other than 'N/A'
            if (isset($data[$field]) && $data[$field] !== 'N/A' && !empty($data[$field])) {
                return $content;
            }
            
            return '';
        }, $message);
    }

    /**
     * Get file data for template processing - only File model data
     */
    public function getFileData($record, array $includeFields = []): array
    {
        $data = [];
        
        // Always include basic File data
        $data['mga_reference'] = $record->mga_reference ?? 'N/A';
        $data['client_reference'] = $record->client_reference ?? 'N/A';
        $data['service_date'] = $record->service_date ? $record->service_date->format('d/m/Y') : 'N/A';
        $data['service_time'] = $record->service_time ?? 'N/A';
        $data['address'] = $record->address ?? 'N/A';
        $data['symptoms'] = $record->symptoms ?? 'N/A';
        $data['diagnosis'] = $record->diagnosis ?? 'N/A';
        $data['email'] = $record->email ?? 'N/A';
        $data['phone'] = $record->phone ?? 'N/A';
        $data['contact_patient'] = $record->contact_patient ?? 'N/A';
        $data['google_drive_link'] = $record->google_drive_link ?? 'N/A';
        $data['status'] = $record->status ?? 'N/A';
        
        // Include related data only if specifically requested
        if (in_array('patient_name', $includeFields)) {
            $data['patient_name'] = $record->patient->name ?? 'N/A';
        }
        
        if (in_array('service_type', $includeFields)) {
            $data['service_type'] = $record->serviceType->name ?? 'N/A';
        }
        
        if (in_array('country', $includeFields)) {
            $data['country'] = $record->country->name ?? 'N/A';
        }
        
        if (in_array('city', $includeFields)) {
            $data['city'] = $record->city->name ?? 'N/A';
        }
        
        if (in_array('provider_branch', $includeFields)) {
            $data['provider_branch'] = $record->providerBranch->branch_name ?? 'N/A';
        }
        
        if (in_array('provider_name', $includeFields)) {
            $data['provider_name'] = $record->providerBranch->provider->name ?? 'N/A';
        }
        
        return $data;
    }

    /**
     * Get ready replies data structure
     */
    public function getReadyReplies(): array
    {
        return [
            'Provider' => [
                'New' => 'We have a new patient requesting a {Service_type}',
                'Available' => 'N/A',
                'Confirmed' => 'The apatient confirmed their availability on {service_date} and {service_time}',
                'Assisted' => 'Thanks',
                'Cancelled' => 'The patient cancelled, Sorry.',
                'Void' => 'The patient cancelled, Sorry.',
            ],
            'Patient' => [
                'New' => 'I am contacting you on behalf of {file->client->name}. We received your medical request for a {service_type} Please confirm your address and let us know you availability ot book you an appointment',
                'Available' => 'We have an appiontment in this address : {file->provider->branch->address} on ((on {Serivce_date} or Today) at {service_time}. Please let us know it thats suits you so that we can confirm the appointment',
                'Confirmed' => 'The appointment has been confirmed (on {Serivce_date} or Today) at {service_time}. Please note we will be charged for a no show.',
                'Assisted' => 'Let us know if you need any help',
                'Cancelled' => 'Noted we will inform your insurance',
                'Void' => 'Noted we will inform your insurance',
            ],
            'Client' => [
                'New' => 'We received the case and we will insert the patient details in our platform and feedback to you with the available appointments details',
                'Available' => 'We have an availble appointment on {file->service_date} at {file->service_Time}. Please send us a GOP for {Gop->where(type="In")->first()->amount} to confirm the appointment',
                'Confirmed' => 'The appointment has been confirmed (on {Serivce_date} or Today) at {service_time}. Please note we will be charged for a no show.',
                'Assisted' => 'Please note that the patient has been assisted and our financial department will send you the invoice shortly',
                'Cancelled' => 'Noted, Case cancelled without any charges',
                'Void' => 'Noted, Case cancelled without any charges',
            ],
        ];
    }

    /**
     * Update ready reply message when category or status changes
     */
    public function updateReadyReplyMessage($set, $record, $get): void
    {
        $category = $get('category');
        $status = $get('status');
        
        if (!$category || !$status) {
            $set('message_display', '');
            return;
        }
        
        $readyReplies = $this->getReadyReplies();
        
        if (!isset($readyReplies[$category][$status])) {
            $set('message_display', 'Message not found for this combination.');
            return;
        }
        
        $message = $readyReplies[$category][$status];
        $processedMessage = $this->processReadyReplyPlaceholders($message, $record);
        
        $set('message_display', $processedMessage);
    }

    /**
     * Process placeholders in ready reply messages
     */
    public function processReadyReplyPlaceholders(string $message, $record): string
    {
        // Handle {Service_type} or {service_type}
        $serviceType = $record->serviceType ? $record->serviceType->name : 'N/A';
        $message = str_replace(['{Service_type}', '{service_type}'], $serviceType, $message);
        
        // Handle {service_date} or {Serivce_date} (typo in original)
        $serviceDate = $record->service_date ? $record->service_date->format('d/m/Y') : 'N/A';
        
        // Handle conditional date: ((on {Serivce_date} or Today) - must be done before regular replacement
        if ($record->service_date && $record->service_date->isToday()) {
            $message = str_replace('(on {Serivce_date} or Today)', 'Today', $message);
            $message = str_replace('((on {Serivce_date} or Today)', 'Today', $message);
        } else {
            $message = str_replace('(on {Serivce_date} or Today)', "(on {$serviceDate})", $message);
            $message = str_replace('((on {Serivce_date} or Today)', "(on {$serviceDate})", $message);
        }
        
        // Now replace regular date placeholders
        $message = str_replace(['{service_date}', '{Serivce_date}'], $serviceDate, $message);
        
        // Handle {service_time} or {service_Time}
        $serviceTime = $record->service_time ?? 'N/A';
        $message = str_replace(['{service_time}', '{service_Time}'], $serviceTime, $message);
        
        // Handle {file->client->name}
        $clientName = ($record->client && $record->client->name) ? $record->client->name : 'N/A';
        $message = str_replace('{file->client->name}', $clientName, $message);
        
        // Handle {file->provider->branch->address}
        $providerBranchAddress = 'N/A';
        if ($record->providerBranch) {
            $providerBranchAddress = $record->providerBranch->address ?? 'N/A';
        }
        $message = str_replace('{file->provider->branch->address}', $providerBranchAddress, $message);
        
        // Handle {file->service_date}
        $message = str_replace('{file->service_date}', $serviceDate, $message);
        
        // Handle {Gop->where(type="In")->first()->amount}
        $gopIn = $record->gops()->where('type', 'In')->first();
        $gopInAmount = $gopIn ? $gopIn->amount : 'N/A';
        $message = str_replace('{Gop->where(type="In")->first()->amount}', $gopInAmount, $message);
        
        return $message;
    }

    /**
     * Show translation options
     */
    public function showTranslationOptions(string $message): void
    {
        // For now, we'll show a simple notification with translation options
        // In a full implementation, you'd want to create a proper modal
        Notification::make()
            ->title('Translation Options')
            ->body('Translation feature is available. Use the copy button and translate externally for now.')
            ->info()
            ->send();
    }

    /**
     * Translate message using Google Translate API
     */
    public function translateMessage(string $message, string $targetLanguage): void
    {
        try {
            // For now, we'll use a simple approach with Google Translate
            // In production, you'd want to use the Google Translate API
            $translatedMessage = $this->simpleTranslate($message, $targetLanguage);
            
            $this->copyToClipboard($translatedMessage, 'Translated Message (' . strtoupper($targetLanguage) . ')');
            
            Notification::make()
                ->title('Translation Complete')
                ->body('Message has been translated to ' . $this->getLanguageName($targetLanguage) . ' and copied to clipboard.')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Translation Failed')
                ->body('Unable to translate message: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get language name from code
     */
    public function getLanguageName(string $code): string
    {
        $languages = [
            'es' => 'Spanish',
            'it' => 'Italian',
            'de' => 'German',
            'fr' => 'French',
        ];
        
        return $languages[$code] ?? $code;
    }

    /**
     * Get current form message based on form state
     */
    public function getCurrentFormMessage($record, $draftMailId, $includeFields, $customNotes): string
    {
        if ($draftMailId) {
            $draftMail = DraftMail::find($draftMailId);
            if ($draftMail) {
                return $this->processTemplate($draftMail->body_mail, $record, $includeFields, $customNotes);
            }
        }
        
        // Fallback to a basic message
        return "Dear " . ($record->patient->name ?? 'Client') . ",\n\nThis is a notification regarding your case.\n\nBest regards,\nMGA Team";
    }



    /**
     * Notify user when phone contact is required for manual follow-up
     */
    private function notifyUserForPhoneContact($providerBranch, $gopContact, $record): void
    {
        // Create a task for the user to call the provider
        Task::create([
            'taskable_id' => $record->id,
            'taskable_type' => \App\Models\File::class,
            'department' => 'Operation',
            'title' => 'Phone Call Required - ' . $providerBranch->branch_name,
            'description' => "Call {$providerBranch->branch_name} to confirm appointment. GOP Contact: {$gopContact->title} - {$gopContact->phone_number}. File: {$record->mga_reference}",
            'due_date' => now()->addHours(2),
            'user_id' => Auth::id(),
            'file_id' => $record->id,
        ]);

        // Send Filament notification to current user
        Notification::make()
            ->title('Manual Follow-up Required')
            ->body("Branch {$providerBranch->branch_name} requires phone confirmation. Please contact manually.")
            ->warning()
            ->send();
    }

    /**
     * Simple translation function (placeholder - replace with actual API)
     */
    public function simpleTranslate(string $message, string $targetLanguage): string
    {
        // This is a placeholder implementation
        // In production, you should use Google Translate API or DeepL API
        
        $translations = [
            'es' => [
                'Dear' => 'Estimado/a',
                'MGA Reference' => 'Referencia MGA',
                'Client Reference' => 'Referencia del Cliente',
                'Service Date' => 'Fecha de Servicio',
                'Service Time' => 'Hora de Servicio',
                'Address' => 'Dirección',
                'Symptoms' => 'Síntomas',
                'Diagnosis' => 'Diagnóstico',
                'Email' => 'Correo Electrónico',
                'Phone' => 'Teléfono',
                'Contact Patient' => 'Contactar Paciente',
                'Google Drive Link' => 'Enlace de Google Drive',
                'Status' => 'Estado',
                'Patient Name' => 'Nombre del Paciente',
                'Service Type' => 'Tipo de Servicio',
                'Country' => 'País',
                'City' => 'Ciudad',
                'Provider Branch' => 'Sucursal del Proveedor',
                'Provider Name' => 'Nombre del Proveedor',
                'Best regards' => 'Saludos cordiales',
                'MGA Team' => 'Equipo MGA',
                'This is a notification regarding your case' => 'Esta es una notificación sobre su caso',
                'No message available' => 'No hay mensaje disponible',
            ],
            'it' => [
                'Dear' => 'Gentile',
                'MGA Reference' => 'Riferimento MGA',
                'Client Reference' => 'Riferimento Cliente',
                'Service Date' => 'Data di Servizio',
                'Service Time' => 'Ora di Servizio',
                'Address' => 'Indirizzo',
                'Symptoms' => 'Sintomi',
                'Diagnosis' => 'Diagnosi',
                'Email' => 'Email',
                'Phone' => 'Telefono',
                'Contact Patient' => 'Contattare Paziente',
                'Google Drive Link' => 'Link Google Drive',
                'Status' => 'Stato',
                'Patient Name' => 'Nome del Paziente',
                'Service Type' => 'Tipo di Servizio',
                'Country' => 'Paese',
                'City' => 'Città',
                'Provider Branch' => 'Filiale Fornitore',
                'Provider Name' => 'Nome Fornitore',
                'Best regards' => 'Cordiali saluti',
                'MGA Team' => 'Team MGA',
                'This is a notification regarding your case' => 'Questa è una notifica riguardo al suo caso',
                'No message available' => 'Nessun messaggio disponibile',
            ],
            'fr' => [
                'Dear' => 'Cher/Chère',
                'MGA Reference' => 'Référence MGA',
                'Client Reference' => 'Référence Client',
                'Service Date' => 'Date de Service',
                'Service Time' => 'Heure de Service',
                'Address' => 'Adresse',
                'Symptoms' => 'Symptômes',
                'Diagnosis' => 'Diagnostic',
                'Email' => 'Email',
                'Phone' => 'Téléphone',
                'Contact Patient' => 'Contacter Patient',
                'Google Drive Link' => 'Lien Google Drive',
                'Status' => 'Statut',
                'Patient Name' => 'Nom du Patient',
                'Service Type' => 'Type de Service',
                'Country' => 'Pays',
                'City' => 'Ville',
                'Provider Branch' => 'Succursale Fournisseur',
                'Provider Name' => 'Nom du Fournisseur',
                'Best regards' => 'Cordialement',
                'MGA Team' => 'Équipe MGA',
                'This is a notification regarding your case' => 'Ceci est une notification concernant votre dossier',
                'No message available' => 'Aucun message disponible',
            ],
            'de' => [
                'Dear' => 'Sehr geehrte/r',
                'MGA Reference' => 'MGA Referenz',
                'Client Reference' => 'Kundenreferenz',
                'Service Date' => 'Servicedatum',
                'Service Time' => 'Servicezeit',
                'Address' => 'Adresse',
                'Symptoms' => 'Symptome',
                'Diagnosis' => 'Diagnose',
                'Email' => 'E-Mail',
                'Phone' => 'Telefon',
                'Contact Patient' => 'Patient kontaktieren',
                'Google Drive Link' => 'Google Drive Link',
                'Status' => 'Status',
                'Patient Name' => 'Patientenname',
                'Service Type' => 'Servicetyp',
                'Country' => 'Land',
                'City' => 'Stadt',
                'Provider Branch' => 'Anbieterfiliale',
                'Provider Name' => 'Anbietername',
                'Best regards' => 'Mit freundlichen Grüßen',
                'MGA Team' => 'MGA Team',
                'This is a notification regarding your case' => 'Dies ist eine Benachrichtigung bezüglich Ihres Falls',
                'No message available' => 'Keine Nachricht verfügbar',
            ],
        ];
        
        $translated = $message;
        if (isset($translations[$targetLanguage])) {
            foreach ($translations[$targetLanguage] as $english => $translatedWord) {
                $translated = str_replace($english, $translatedWord, $translated);
            }
        }
        
        return $translated;
    }

    private function getPreferredContactDisplay($branch)
    {
        // Simple approach - just show the preferred contact method
        $operationContact = $branch->operationContact;
        
        if (!$operationContact) {
            return 'N/A';
        }

        // Just return the preferred contact method
        return $operationContact->preferred_contact ?? 'N/A';
    }

    private function getDistanceToBranch($file, $branch)
    {
        if (!$file->address) {
            return 'N/A';
        }

        $operationContact = $branch->operationContact;
        if (!$operationContact || !$operationContact->address) {
            return 'N/A';
        }

        $distanceService = app(\App\Services\DistanceCalculationService::class);
        $distanceData = $distanceService->calculateDistance($file->address, $operationContact->address);
        
        return $distanceService->getFormattedDistance($distanceData);
    }

    public function updateBranches()
    {
        // This method will be called when filters change
        // The form will automatically update the branches list
    }

    /**
     * Get provider branch options for the checkbox list
     */
    protected function getProviderBranchOptions($record): array
    {
        $branches = $this->getEligibleProviderBranches($record);
        $options = [];
        
        foreach ($branches as $branch) {
            $options[$branch->id] = $branch->branch_name;
        }
        
        return $options;
    }

    /**
     * Get provider branch descriptions for the checkbox list
     */
    protected function getProviderBranchDescriptions($record): array
    {
        $branches = $this->getEligibleProviderBranches($record);
        $descriptions = [];
        
        foreach ($branches as $branch) {
            $description = [];
            
            // Add city
            if ($branch->city) {
                $description[] = "City: " . $branch->city->name;
            }
            
            // Add priority
            $description[] = "Priority: " . ($branch->priority ?? 'N/A');
            
            // Add cost
            $service = $branch->services()
                ->where('service_type_id', $record->service_type_id)
                ->first();
            if ($service) {
                $minCost = $service->pivot->min_cost;
                $maxCost = $service->pivot->max_cost;
                
                if ($minCost && $maxCost) {
                    if ($minCost == $maxCost) {
                        $description[] = "Cost: €" . number_format($minCost, 2);
                    } else {
                        $description[] = "Cost: €" . number_format($minCost, 2) . " - €" . number_format($maxCost, 2);
                    }
                } elseif ($minCost) {
                    $description[] = "Cost: €" . number_format($minCost, 2);
                } elseif ($maxCost) {
                    $description[] = "Cost: €" . number_format($maxCost, 2);
                }
            }
            
            // Add distance if available
            $distance = $this->calculateBranchDistance($record, $branch);
            if ($distance !== 'N/A') {
                $description[] = "Distance: " . $distance;
            }
            
            // Add contact info
            $contactInfo = $this->getBranchContactInfo($branch);
            $description[] = "Contact: " . $contactInfo;
            
            $descriptions[$branch->id] = implode(' | ', $description);
        }
        
        return $descriptions;
    }

    /** Maximum number of provider branches to show in the Request Appointment modal */
    protected static int $requestAppointmentBranchesLimit = 8;

    /**
     * Get the displayed (sorted, limited) provider branches for the Request Appointment modal.
     */
    protected function getDisplayedProviderBranchesForRequest($cityFilter = null, int $limit = null): \Illuminate\Support\Collection
    {
        $limit = $limit ?? static::$requestAppointmentBranchesLimit;
        $branches = $this->getEligibleProviderBranches($this->record, $cityFilter);
        $branchesWithSortData = $branches->map(function ($branch) {
            $distanceData = $this->calculateBranchDistanceForSorting($branch);
            $serviceTypeId = $this->record->service_type_id ?? 999;
            $statusSort = $branch->status === 'Active' ? 1 : 2;
            $branch->sort_distance = $distanceData['sort_value'];
            $branch->distance_display = $distanceData['display'];
            $branch->sort_service_type = $serviceTypeId;
            $branch->sort_status = $statusSort;
            return $branch;
        });
        return $branchesWithSortData->sortBy([
            ['sort_distance', 'asc'],
            ['sort_service_type', 'asc'],
            ['sort_status', 'asc'],
        ])->values()->take($limit);
    }

    /**
     * Get eligible provider branches for a file
     * Filters by city, service type, and active status
     * Uses the original availableBranches logic but with optional city filter
     */
    protected function getEligibleProviderBranches($record, $cityId = null)
    {
        $serviceTypeId = $record->service_type_id;
        
        // Use city filter from parameter, fallback to file's city
        $filterCityId = $cityId ?? $record->city_id;
        
        // If service type is 2 (telemedicine), ignore city filters
        if ($record->service_type_id == 2) {
            return \App\Models\ProviderBranch::query()
                ->where('status', 'Active')
                ->whereHas('services', function ($q) use ($serviceTypeId) {
                    $q->where('service_type_id', $serviceTypeId);
                })
                ->with(['provider', 'city', 'services', 'gopContact', 'operationContact'])
                ->get();
        }
        
        // If no country is assigned, show all branches with matching service type
        if (!$record->country_id) {
            return \App\Models\ProviderBranch::query()
                ->where('status', 'Active')
                ->whereHas('services', function ($q) use ($serviceTypeId) {
                    $q->where('service_type_id', $serviceTypeId);
                })
                ->with(['provider', 'city', 'services', 'gopContact', 'operationContact'])
                ->get();
        }
        
        // Filter branches by city (direct or via pivot) or all_country
        $query = \App\Models\ProviderBranch::query()
            ->where('status', 'Active')
            ->whereHas('services', function ($q) use ($serviceTypeId) {
                $q->where('service_type_id', $serviceTypeId);
            })
            ->whereHas('provider', function ($q) use ($record) {
                // Filter by country - provider must be in the file's country
                $q->where('country_id', $record->country_id);
            })
            ->with(['provider', 'city', 'services', 'gopContact', 'operationContact']);
        
        // Filter by city if provided
        if ($filterCityId) {
            $query->where(function ($q) use ($filterCityId) {
                // Filter by city - branch serves this city in any way
                $q->where('all_country', true)
                  // OR branches assigned to this city via many-to-many relationship (branch_cities table)
                  ->orWhereHas('cities', fn ($q) => $q->where('cities.id', $filterCityId));
            });
        }
        
        // Don't order here - sorting will be done by distance in getBranchRows
        return $query->get();
    }

    /**
     * Calculate distance between file and branch
     */
    protected function calculateBranchDistance($file, $branch): string
    {
        if (!$file->address || !$branch->address) {
            return 'N/A';
        }

        $distanceService = new \App\Services\DistanceCalculationService();
        $result = $distanceService->calculateDistance(
            $file->address,
            $branch->address,
            'driving'
        );

        if ($result) {
            return "{$result['distance']} - {$result['duration']}";
        }

        return '35 min walking';
    }

    /**
     * Get branch contact info display from branch table directly
     */
    protected function getBranchContactInfo($branch): string
    {
        $hasEmail = !empty($branch->email);
        $hasPhone = !empty($branch->phone);
        
        if ($hasEmail && $hasPhone) {
            return 'Email, <button type="button" class="text-blue-600 cursor-pointer hover:underline" wire:click="showPhoneNotification(\'' . $branch->phone . '\', \'' . $branch->branch_name . '\')">Phone</button>';
        } elseif ($hasEmail) {
            return 'Email';
        } elseif ($hasPhone) {
            return '<button type="button" class="text-blue-600 cursor-pointer hover:underline" wire:click="showPhoneNotification(\'' . $branch->phone . '\', \'' . $branch->branch_name . '\')">Phone</button>';
        }
        
        return 'None';
    }

    /**
     * Get branch distance information
     */
    protected function getBranchDistanceInfo($branch): string
    {
        if (! (bool) config('services.google.distance_enabled', true)) {
            return '<span class="text-gray-500 text-sm">N/A</span>';
        }

        if (!$this->record || !$this->record->address) {
            return '<span class="text-gray-400 text-sm">No file address</span>';
        }

        // Try branch address first, then operation contact address (same logic as DistanceCalculationService)
        $branchAddress = $branch->address ?? $branch->operationContact?->address;
        
        if (!$branchAddress) {
            return '<span class="text-gray-400 text-sm">No branch address</span>';
        }

        try {
            $distanceService = new \App\Services\DistanceCalculationService();
            
            // Check if API key is configured
            $apiKey = config('services.google.maps_api_key');
            if (empty($apiKey)) {
                \Illuminate\Support\Facades\Log::warning('Google Maps API key not configured', [
                    'branch_id' => $branch->id,
                    'file_id' => $this->record->id
                ]);
                return '<span class="text-yellow-600 text-sm">API key not configured</span>';
            }
            
            // Calculate driving distance
            $drivingDistance = $distanceService->calculateDistance(
                $this->record->address,
                $branchAddress,
                'driving'
            );
            $drivingError = $distanceService->getLastError();
            
            // Calculate walking distance
            $walkingDistance = $distanceService->calculateDistance(
                $this->record->address,
                $branchAddress,
                'walking'
            );
            $walkingError = $distanceService->getLastError();

            $result = [];
            
            if ($drivingDistance) {
                // Use duration_minutes (time) not distance - convert from seconds if needed
                $minutes = null;
                if (isset($drivingDistance['duration_minutes'])) {
                    $minutes = $drivingDistance['duration_minutes'];
                } elseif (isset($drivingDistance['duration_seconds'])) {
                    // Calculate minutes from seconds if duration_minutes not available
                    $minutes = round($drivingDistance['duration_seconds'] / 60, 1);
                }
                
                if ($minutes !== null) {
                    // Format: "X.X min by 🚗" - showing TIME, not distance
                    $result[] = number_format($minutes, 1) . " min by 🚗";
                }
            }
            
            if ($walkingDistance) {
                // Use duration_minutes (time) not distance - convert from seconds if needed
                $minutes = null;
                if (isset($walkingDistance['duration_minutes'])) {
                    $minutes = $walkingDistance['duration_minutes'];
                } elseif (isset($walkingDistance['duration_seconds'])) {
                    // Calculate minutes from seconds if duration_minutes not available
                    $minutes = round($walkingDistance['duration_seconds'] / 60, 1);
                }
                
                if ($minutes !== null) {
                    // Format: "X.X min by 🚶" - showing TIME, not distance
                    $result[] = number_format($minutes, 1) . " min by 🚶";
                }
            }

            if (empty($result)) {
                // Get error details from the service (prefer the most recent, or combine if both failed)
                $errorMessage = $walkingError ?: $drivingError;
                
                // Log the issue for debugging
                \Illuminate\Support\Facades\Log::warning('Distance calculation returned empty', [
                    'branch_id' => $branch->id,
                    'branch_address' => $branchAddress,
                    'file_id' => $this->record->id,
                    'file_address' => $this->record->address,
                    'api_key_configured' => !empty($apiKey),
                    'last_error' => $errorMessage
                ]);
                
                $displayMessage = 'Distance unavailable';
                if ($errorMessage) {
                    // Show full error message but wrap it nicely
                    $displayMessage .= '<br><span class="text-xs block mt-1">' . htmlspecialchars($errorMessage) . '</span>';
                } else {
                    $displayMessage .= '<br><span class="text-xs">Check API key & addresses</span>';
                }
                
                return '<span class="text-yellow-600 text-sm">' . $displayMessage . '</span>';
            }

            return '<ul class="list-disc list-inside space-y-1">' . 
                   implode('', array_map(fn($item) => '<li>' . $item . '</li>', $result)) . 
                   '</ul>';
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Distance calculation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'branch_id' => $branch->id,
                'branch_address' => $branchAddress ?? 'N/A',
                'file_id' => $this->record->id,
                'file_address' => $this->record->address ?? 'N/A'
            ]);
            return '<span class="text-red-600 text-sm">Calculation error<br><span class="text-xs">Check logs</span></span>';
        }
    }

    /**
     * Get branch rows for table-like display
     */
    /**
     * Calculate branch distance for sorting purposes
     * Returns both sort value (numeric minutes) and display string
     */
    protected function calculateBranchDistanceForSorting($branch): array
    {
        if (!$this->record || !$this->record->address) {
            return [
                'sort_value' => 999999, // Put at end when sorting
                'display' => '<span class="text-gray-400 text-sm">No file address</span>'
            ];
        }

        $branchAddress = $branch->address ?? $branch->operationContact?->address;
        
        if (!$branchAddress) {
            return [
                'sort_value' => 999999,
                'display' => '<span class="text-gray-400 text-sm">No branch address</span>'
            ];
        }

        try {
            $distanceService = new \App\Services\DistanceCalculationService();
            $apiKey = config('services.google.maps_api_key');
            
            if (empty($apiKey)) {
                return [
                    'sort_value' => 999999,
                    'display' => '<span class="text-yellow-600 text-sm">API key not configured</span>'
                ];
            }
            
            // Calculate driving distance (preferred for sorting)
            $drivingDistance = $distanceService->calculateDistance(
                $this->record->address,
                $branchAddress,
                'driving'
            );
            
            if ($drivingDistance) {
                $minutes = null;
                if (isset($drivingDistance['duration_minutes'])) {
                    $minutes = $drivingDistance['duration_minutes'];
                } elseif (isset($drivingDistance['duration_seconds'])) {
                    $minutes = round($drivingDistance['duration_seconds'] / 60, 1);
                }
                
                if ($minutes !== null) {
                    return [
                        'sort_value' => $minutes,
                        'display' => $this->getBranchDistanceInfo($branch) // Use existing method for display
                    ];
                }
            }
            
            // Fallback: try walking distance
            $walkingDistance = $distanceService->calculateDistance(
                $this->record->address,
                $branchAddress,
                'walking'
            );
            
            if ($walkingDistance) {
                $minutes = null;
                if (isset($walkingDistance['duration_minutes'])) {
                    $minutes = $walkingDistance['duration_minutes'];
                } elseif (isset($walkingDistance['duration_seconds'])) {
                    $minutes = round($walkingDistance['duration_seconds'] / 60, 1);
                }
                
                if ($minutes !== null) {
                    return [
                        'sort_value' => $minutes,
                        'display' => $this->getBranchDistanceInfo($branch)
                    ];
                }
            }
            
            // No distance available
            return [
                'sort_value' => 999999,
                'display' => $this->getBranchDistanceInfo($branch)
            ];
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Distance calculation error', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'sort_value' => 999999,
                'display' => '<span class="text-gray-400 text-sm">Distance unavailable</span>'
            ];
        }
    }

    protected function getBranchRows(): array
    {
        // Get city filter from form state
        // In Filament actions, when form re-renders due to ->live(), 
        // the form state is available through the Livewire component's data
        $cityFilter = null;
        
        // Try to get from mounted action data
        // The structure varies, so we try multiple approaches
        try {
            if (property_exists($this, 'mountedActionsData') && isset($this->mountedActionsData['city_filter'])) {
                $cityFilter = $this->mountedActionsData['city_filter'];
            } elseif (property_exists($this, 'mountedActions') && isset($this->mountedActions['requestAppointment']['data']['city_filter'])) {
                $cityFilter = $this->mountedActions['requestAppointment']['data']['city_filter'];
            }
        } catch (\Exception $e) {
            // If we can't access form state, use null (will use file's city)
            $cityFilter = null;
        }
        
        $sortedBranches = $this->getDisplayedProviderBranchesForRequest($cityFilter);
        
        $rows = [];
        
        foreach ($sortedBranches as $branch) {
            $rows[] = Grid::make(12)
                ->schema([
                    // Checkbox column
                    Checkbox::make("branch_{$branch->id}")
                        ->label('')
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $get) use ($branch) {
                            $selectedBranches = $get('selected_branches') ?? [];
                            if ($state) {
                                if (!in_array($branch->id, $selectedBranches)) {
                                    $selectedBranches[] = $branch->id;
                                }
                            } else {
                                $selectedBranches = array_filter($selectedBranches, fn($id) => $id != $branch->id);
                            }
                            $set('selected_branches', array_values($selectedBranches));
                            
                            // Update "Select All" checkbox state (only for displayed branches)
                            $cityFilter = $get('city_filter');
                            $displayedBranches = $this->getDisplayedProviderBranchesForRequest($cityFilter);
                            $totalBranches = $displayedBranches->count();
                            $selectedCount = count($selectedBranches);
                            
                            if ($selectedCount === 0) {
                                $set('select_all_branches', false);
                            } elseif ($selectedCount === $totalBranches) {
                                $set('select_all_branches', true);
                            } else {
                                $set('select_all_branches', false); // Partial selection
                            }
                        })
                        ->columnSpan(1),
                    
                    // Branch name column (with provider name and comment as description)
                    \Filament\Forms\Components\View::make('branch_name_' . $branch->id)
                        ->view('filament.forms.components.branch-name-link')
                        ->viewData([
                            'branchName' => $branch->branch_name,
                            'branchId' => $branch->id,
                            'providerName' => $branch->provider?->name ?? null,
                            'providerComment' => $branch->provider?->comment ?? null
                        ])
                        ->columnSpan(2),
                    
                    // Priority column
                    \Filament\Forms\Components\Placeholder::make("priority_{$branch->id}")
                        ->label('')
                        ->content($branch->priority ?? 'N/A')
                        ->extraAttributes(['class' => 'text-sm leading-tight'])
                        ->columnSpan(1),
                    
                    // Cost column (only show appointment cost, not selling cost)
                    \Filament\Forms\Components\Placeholder::make("cost_{$branch->id}")
                        ->label('')
                        ->content(function () use ($branch) {
                            if ($this->record && $this->record->service_type_id) {
                                $service = $branch->services()
                                    ->where('service_type_id', $this->record->service_type_id)
                                    ->first();
                                if ($service) {
                                    $minCost = $service->pivot->min_cost;
                                    
                                    // Only show min_cost (appointment cost), not max_cost (selling cost)
                                    if ($minCost) {
                                        return '€' . number_format($minCost, 2);
                                    }
                                }
                            }
                            return 'N/A';
                        })
                        ->extraAttributes(['class' => 'text-sm leading-tight'])
                        ->columnSpan(1),
                    
                    // Communication method column
                    \Filament\Forms\Components\Placeholder::make("communication_{$branch->id}")
                        ->label('')
                        ->content($branch->communication_method ?? 'N/A')
                        ->extraAttributes(['class' => 'text-sm leading-tight'])
                        ->columnSpan(1),
                    
                    // Contact column
                    \Filament\Forms\Components\View::make('contact_' . $branch->id)
                        ->view('filament.forms.components.contact-info')
                        ->viewData([
                            'contactInfo' => $this->getBranchContactInfo($branch),
                            'branchId' => $branch->id
                        ])
                        ->columnSpan(1),
                    
                    // Phone column (copiable)
                    \Filament\Forms\Components\View::make('phone_' . $branch->id)
                        ->view('filament.forms.components.copiable-field')
                        ->viewData([
                            'label' => 'phone',
                            'value' => $branch->phone ?? ($branch->getPrimaryPhoneAttribute() ?? 'N/A')
                        ])
                        ->columnSpan(1),
                    
                    // Address column (copiable)
                    \Filament\Forms\Components\View::make('address_' . $branch->id)
                        ->view('filament.forms.components.copiable-field')
                        ->viewData([
                            'label' => 'address',
                            'value' => $branch->address ?? 'N/A'
                        ])
                        ->columnSpan(1),
                    
                    // Website column (copiable)
                    \Filament\Forms\Components\View::make('website_' . $branch->id)
                        ->view('filament.forms.components.copiable-field')
                        ->viewData([
                            'label' => 'website',
                            'value' => $branch->website ?? 'N/A'
                        ])
                        ->columnSpan(1),
                    
                    // Distance column (disabled - plain N/A only)
                    \Filament\Forms\Components\Placeholder::make('distance')
                        ->label('')
                        ->content('N/A')
                        ->extraAttributes(['class' => 'text-sm leading-tight'])
                        ->columnSpan(1),
                    
                    // Request column (clickable to copy appointment details)
                    \Filament\Forms\Components\View::make('request_' . $branch->id)
                        ->view('filament.forms.components.request-appointment')
                        ->viewData([
                            'branch' => $branch,
                            'record' => $this->record,
                            'appointmentText' => $this->formatAppointmentRequestText($branch)
                        ])
                        ->columnSpan(1),
                ])
                ->extraAttributes(['class' => 'border-b border-gray-100 hover:bg-gray-50']);
        }
        
        return $rows;
    }

    /**
     * Get branch description for modal display
     */
    protected function getBranchDescription($branch): string
    {
        $description = [];
        
        // Add provider name
        if ($branch->provider) {
            $description[] = "Provider: " . $branch->provider->name;
        }
        
        // Add city
        if ($branch->city) {
            $description[] = "City: " . $branch->city->name;
        }
        
        // Add priority with color coding
        $priority = $branch->priority ?? 'N/A';
        if ($priority !== 'N/A') {
            if ($priority <= 3) {
                $description[] = "Priority: " . $priority . " (High)";
            } elseif ($priority <= 6) {
                $description[] = "Priority: " . $priority . " (Medium)";
            } else {
                $description[] = "Priority: " . $priority . " (Low)";
            }
        } else {
            $description[] = "Priority: N/A";
        }
        
        // Add cost for current service type
        if ($this->record && $this->record->service_type_id) {
            $service = $branch->services()
                ->where('service_type_id', $this->record->service_type_id)
                ->first();
            if ($service) {
                $minCost = $service->pivot->min_cost;
                $maxCost = $service->pivot->max_cost;
                
                if ($minCost || $maxCost) {
                    $cheapestCost = $minCost ?: $maxCost;
                    $description[] = "From: €" . number_format($cheapestCost, 2);
                } else {
                    $description[] = "No pricing available";
                }
            } else {
                $description[] = "No pricing available";
            }
        }
        
        // Add contact info
        $contactInfo = $this->getBranchContactInfo($branch);
        $description[] = "Contact: " . $contactInfo;
        
        return implode(' • ', $description);
    }

    /**
     * Send appointment requests from modal
     */
    protected function sendAppointmentRequestsFromModal(array $data, $record): void
    {
        $selectedBranchIds = $data['selected_branches'] ?? [];
        $customEmails = collect($data['custom_emails'] ?? [])->pluck('email')->filter();
        
        // If no branches selected but custom emails provided, send to custom emails only
        if (empty($selectedBranchIds) && $customEmails->isNotEmpty()) {
            try {
                // Send email to custom recipients only
                Mail::send(new \App\Mail\AppointmentRequestMailable($record, null, $customEmails->toArray()));
                
                Notification::make()
                    ->title('Appointment Request Sent')
                    ->body("✅ Successfully sent to {$customEmails->count()} custom email recipients")
                    ->success()
                    ->send();
                return;
            } catch (\Exception $e) {
                Log::error('Failed to send appointment request to custom emails', [
                    'error' => $e->getMessage(),
                    'custom_emails' => $customEmails->toArray()
                ]);
                
                Notification::make()
                    ->title('Failed to Send')
                    ->body('Failed to send appointment request to custom emails.')
                    ->danger()
                    ->send();
                return;
            }
        }
        
        // If no branches selected and no custom emails, show warning
        if (empty($selectedBranchIds)) {
            Notification::make()
                ->title('No Recipients Selected')
                ->body('Please select at least one provider branch or add custom email recipients.')
                ->warning()
                ->send();
            return;
        }

        $successCount = 0;
        $failureCount = 0;
        $branches = \App\Models\ProviderBranch::whereIn('id', $selectedBranchIds)->get();

        foreach ($branches as $branch) {
            try {
                // Check if branch has email or if we have custom emails
                $hasBranchEmail = !empty($branch->email);
                $hasCustomEmails = $customEmails->isNotEmpty();
                
                if (!$hasBranchEmail && !$hasCustomEmails) {
                    // No email available, create task for manual follow-up
                    $this->createManualFollowUpTaskForBranch($branch, $record);
                    $failureCount++;
                    continue;
                }

                // Send email using AppointmentRequestMailable
                Mail::send(new \App\Mail\AppointmentRequestMailable($record, $branch, $customEmails->toArray()));
                
                $successCount++;
                
            } catch (\Exception $e) {
                Log::error('Failed to send appointment request', [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->branch_name,
                    'error' => $e->getMessage()
                ]);
                $failureCount++;
            }
        }

        // Show notification
        if ($successCount > 0) {
            Notification::make()
                ->title('Appointment Requests Sent')
                ->body("✅ Successfully sent to {$successCount} providers")
                ->success()
                ->send();
        }

        if ($failureCount > 0) {
            Notification::make()
                ->title('Some Requests Failed')
                ->body("⚠️ Failed to send to {$failureCount} providers (manual follow-up tasks created)")
                ->warning()
                ->send();
        }
    }

    /**
     * Create manual follow-up task for branch
     */
    protected function createManualFollowUpTaskForBranch($branch, $record): void
    {
        Task::create([
            'title' => "Manual follow-up required for appointment request",
            'description' => "File: {$record->mga_reference} - Patient: {$record->patient->name} - Branch: {$branch->branch_name}",
            'taskable_type' => \App\Models\ProviderBranch::class,
            'taskable_id' => $branch->id,
            'assigned_to' => Auth::id(),
            'due_date' => now()->addDays(1),
            'priority' => 'high',
            'status' => 'pending'
        ]);
    }

    /**
     * Format appointment request text for clipboard
     */
    protected function formatAppointmentRequestText($branch): string
    {
        // Address
        $address = $branch->address ?? 'N/A';
        
        // Distance - extract from distance calculation
        $distanceText = 'N/A';
        try {
            $distanceData = $this->calculateBranchDistanceForSorting($branch);
            if (isset($distanceData['display'])) {
                // Extract text from HTML if needed, or use a simple format
                // The user wants format like "20Mins by car"
                if ($distanceData['sort_value'] < 999999) {
                    $minutes = round($distanceData['sort_value'], 0);
                    $distanceText = "{$minutes}Mins by car";
                }
            }
        } catch (\Exception $e) {
            $distanceText = 'N/A';
        }
        
        // Branch name
        $branchName = $branch->branch_name ?? 'N/A';
        
        // Date & Time
        $dateTime = 'N/A';
        if ($this->record) {
            $dateParts = [];
            if ($this->record->service_date) {
                $dateParts[] = \Carbon\Carbon::parse($this->record->service_date)->format('d/m/Y');
            }
            if ($this->record->service_time) {
                $timeParts = explode(':', $this->record->service_time);
                if (count($timeParts) >= 2) {
                    $dateParts[] = $timeParts[0] . ':' . $timeParts[1];
                }
            }
            $dateTime = !empty($dateParts) ? implode(' at ', $dateParts) : 'N/A';
        }
        
        // Get service type and branch service data
        $serviceTypeId = $this->record->service_type_id ?? null;
        $minCost = null;
        $maxCost = null; // This is the "selling price"
        $cost = 'N/A';
        $fileFeeText = '';
        $gop = 'N/A';
        
        if ($this->record && $serviceTypeId) {
            $service = $branch->services()
                ->where('service_type_id', $serviceTypeId)
                ->first();
            
            if ($service) {
                $minCost = $service->pivot->min_cost;
                $maxCost = $service->pivot->max_cost; // This is the selling price
            }
        }
        
        // Get file fee from file_fees table for the service type
        $fileFeeAmount = $this->getFileFeeForServiceType($serviceTypeId);
        
        // Calculate based on service type
        if ($serviceTypeId == 2) {
            // Telemedicine: Only cost and GOP = File Fee amount
            if ($fileFeeAmount) {
                $cost = number_format($fileFeeAmount, 0) . '€';
                $gop = number_format($fileFeeAmount, 0) . '€';
                $fileFeeText = ''; // No file fee text for telemedicine
            } else {
                $cost = 'N/A';
                $gop = 'N/A';
            }
        } elseif ($serviceTypeId == 1) {
            // House Call: Round to nearest 100€ (special logic: <200→300, then round up to next 100€)
            if ($minCost || $maxCost) {
                $baseCost = $minCost ?? $maxCost ?? 0;
                // Special rounding logic for House Call
                $roundedCost = $this->roundHouseCallCost($baseCost);
                $cost = number_format($roundedCost, 0) . '€';
                $gop = number_format($roundedCost, 0) . '€';
                $fileFeeText = ''; // No file fee for house call
            }
        } else {
            // Any other service type
            if (empty($maxCost)) {
                // If selling price (max_cost) is empty, use min_cost
                if ($minCost) {
                    $cost = number_format($minCost, 0) . '€';
                    
                    // Calculate file fee: for each 250€, add one multiple of file fee
                    if ($fileFeeAmount) {
                        $fileFeeMultiplier = ceil($minCost / 250);
                        $calculatedFileFee = $fileFeeAmount * $fileFeeMultiplier;
                        $fileFeeText = ' + ' . number_format($calculatedFileFee, 0) . '€ file fee';
                        $gop = number_format($minCost + $calculatedFileFee, 0) . '€';
                    } else {
                        // Try to get Clinic Visit file fee as substitute
                        $clinicVisitFileFee = $this->getFileFeeForClinicVisit();
                        if ($clinicVisitFileFee) {
                            $fileFeeMultiplier = ceil($minCost / 250);
                            $calculatedFileFee = $clinicVisitFileFee * $fileFeeMultiplier;
                            $fileFeeText = ' + ' . number_format($calculatedFileFee, 0) . '€ file fee';
                            $gop = number_format($minCost + $calculatedFileFee, 0) . '€';
                        } else {
                            $fileFeeText = '';
                            $gop = number_format($minCost, 0) . '€';
                        }
                    }
                }
            } else {
                // If selling price (max_cost) is not empty, use it and add file fee (MULTIPLIED)
                $cost = number_format($maxCost, 0) . '€';
                
                if ($fileFeeAmount) {
                    // File fee should be multiplied for all services except house call and telemedicine
                    $fileFeeMultiplier = ceil($maxCost / 250);
                    $calculatedFileFee = $fileFeeAmount * $fileFeeMultiplier;
                    $fileFeeText = ' + ' . number_format($calculatedFileFee, 0) . '€ file fee';
                    $gop = number_format($maxCost + $calculatedFileFee, 0) . '€';
                } else {
                    // Try Clinic Visit file fee as substitute
                    $clinicVisitFileFee = $this->getFileFeeForClinicVisit();
                    if ($clinicVisitFileFee) {
                        $fileFeeMultiplier = ceil($maxCost / 250);
                        $calculatedFileFee = $clinicVisitFileFee * $fileFeeMultiplier;
                        $fileFeeText = ' + ' . number_format($calculatedFileFee, 0) . '€ file fee';
                        $gop = number_format($maxCost + $calculatedFileFee, 0) . '€';
                    } else {
                        $fileFeeText = '';
                        $gop = number_format($maxCost, 0) . '€';
                    }
                }
            }
        }
        
        // Format the text
        $text = "Address: {$address}\n";
        $text .= "Distance: {$distanceText}\n";
        $text .= "Name: {$branchName}\n";
        $text .= "Date & Time: {$dateTime}\n";
        $text .= "Cost: {$cost}{$fileFeeText}\n";
        $text .= "Requested GOP: {$gop}";
        
        return $text;
    }

    /**
     * Round House Call cost to nearest 100€ with special logic
     * < 200€ → 300€
     * 200-299€ → 400€
     * 300-399€ → 500€
     * 400-499€ → 600€
     * etc.
     */
    protected function roundHouseCallCost(float $cost): float
    {
        if ($cost < 200) {
            return 300;
        }
        // Round up to next 100€
        return ceil($cost / 100) * 100;
    }

    /**
     * Get file fee for a specific service type (with priority matching)
     */
    protected function getFileFeeForServiceType(?int $serviceTypeId): ?float
    {
        if (!$serviceTypeId || !$this->record) {
            return null;
        }

        $countryId = $this->record->country_id;
        $cityId = $this->record->city_id;

        // Priority 1: service_type + country + city
        if ($countryId && $cityId) {
            $fileFee = FileFee::where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->where('city_id', $cityId)
                ->first();
            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }

        // Priority 2: service_type + country
        if ($countryId) {
            $fileFee = FileFee::where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->whereNull('city_id')
                ->first();
            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }

        // Priority 3: service_type only
        $fileFee = FileFee::where('service_type_id', $serviceTypeId)
            ->whereNull('country_id')
            ->whereNull('city_id')
            ->first();
        if ($fileFee) {
            return (float) $fileFee->amount;
        }

        return null;
    }

    /**
     * Get file fee for Clinic Visit service type (used as substitute)
     */
    protected function getFileFeeForClinicVisit(): ?float
    {
        if (!$this->record) {
            return null;
        }

        // Find Clinic Visit service type (ID 5)
        $clinicVisitServiceTypeId = 5;
        $countryId = $this->record->country_id;
        $cityId = $this->record->city_id;

        // Priority 1: Clinic Visit + country + city
        if ($countryId && $cityId) {
            $fileFee = FileFee::where('service_type_id', $clinicVisitServiceTypeId)
                ->where('country_id', $countryId)
                ->where('city_id', $cityId)
                ->first();
            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }

        // Priority 2: Clinic Visit + country
        if ($countryId) {
            $fileFee = FileFee::where('service_type_id', $clinicVisitServiceTypeId)
                ->where('country_id', $countryId)
                ->whereNull('city_id')
                ->first();
            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }

        // Priority 3: Clinic Visit only
        $fileFee = FileFee::where('service_type_id', $clinicVisitServiceTypeId)
            ->whereNull('country_id')
            ->whereNull('city_id')
            ->first();
        if ($fileFee) {
            return (float) $fileFee->amount;
        }

        return null;
    }

    /**
     * Show phone notification
     */
    public function showPhoneNotification($phoneNumber, $branchName): void
    {
        Notification::make()
            ->title("📞 {$branchName}'s Phone Number")
            ->body("Phone: {$phoneNumber}")
            ->success()
            ->persistent()
            ->send();
    }
}
