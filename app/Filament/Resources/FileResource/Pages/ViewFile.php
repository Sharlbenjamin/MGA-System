<?php

namespace App\Filament\Resources\FileResource\Pages;

use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Filament\Infolists\Infolist;
use App\Filament\Resources\FileResource;
use Filament\Infolists\Components\Card;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Components\HtmlEntry;
use Filament\Actions\Action;
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

class ViewFile extends ViewRecord
{
    protected static string $resource = FileResource::class;

    public function getTitle(): string
    {
        return $this->record->mga_reference;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(1)
            ->schema([
                Tabs::make('FileTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Overview')
                            ->schema([
                                InfolistSection::make()
                                    ->columns(3)
                                    ->schema([
                        // Column 1: Patient & Client Info (Condensed)
                        Card::make()
                            ->schema([
                                    TextEntry::make('mga_reference')
                                        ->label('MGA Reference')
                                        ->color('warning')
                                        ->weight('bold')
                                        ->size('lg')
                                        ->suffixAction(
                                            InfolistAction::make('copy_mga_reference')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $formattedInfo = $this->formatCaseInfo($record);
                                                    $this->copyToClipboard($formattedInfo, 'Case Information');
                                                })
                                        ),
                                    TextEntry::make('patient.name')
                                        ->label('Patient Name')
                                        ->weight('bold')
                                        ->color('danger')
                                        ->suffixAction(
                                            InfolistAction::make('copy_patient_name')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = $record->patient->name;
                                                    $this->copyToClipboard($text, 'Patient Name');
                                                })
                                        ),
                                    TextEntry::make('patient.client.company_name')
                                        ->label('Client Name')
                                        ->weight('bold')
                                        ->color('success')
                                        ->suffixAction(
                                            InfolistAction::make('copy_client_name')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = $record->patient->client->company_name;
                                                    $this->copyToClipboard($text, 'Client Name');
                                                })
                                        ),
                                    TextEntry::make('patient.dob')
                                        ->color('danger')
                                        ->label('Age')
                                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y')),
                                        
                                    TextEntry::make('client_reference')
                                        ->label('Client Reference')
                                        ->color('success')
                                        ->suffixAction(
                                            InfolistAction::make('copy_client_reference')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = $record->client_reference;
                                                    $this->copyToClipboard($text, 'Client Reference');
                                                })
                                        ),
                                    TextEntry::make('patient.gender')
                                        ->label('Gender')
                                        ->color('danger')
                                        ->suffixAction(
                                            InfolistAction::make('copy_patient_gender')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = $record->patient->gender;
                                                    $this->copyToClipboard($text, 'Patient Gender');
                                                })
                                        ),
                                    TextEntry::make('email')
                                        ->label('Email')
                                        ->color('danger')
                                        ->suffixAction(
                                            InfolistAction::make('copy_email')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = $record->email;
                                                    $this->copyToClipboard($text, 'Email');
                                                })
                                        ),
                                    TextEntry::make('phone')
                                        ->label('Phone')
                                        ->color('danger')
                                        ->suffixAction(
                                            InfolistAction::make('copy_phone')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = $record->phone;
                                                    $this->copyToClipboard($text, 'Phone');
                                                })
                                        ),
                                ])
                                ->columnSpan(1),

                        // Column 2: Service & Provider Info (Condensed)
                        Card::make()
                            ->schema([
                                TextEntry::make('serviceType.name')
                                    ->label('Service Type')
                                    ->weight('bold')
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_service_type')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->serviceType->name;
                                                $this->copyToClipboard($text, 'Service Type');
                                            })
                                    ),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->suffixAction(
                                        InfolistAction::make('copy_status')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->status;
                                                $this->copyToClipboard($text, 'Status');
                                            })
                                    ),
                                TextEntry::make('providerBranch.provider.name')
                                    ->label('Provider Name')
                                    ->color('info')
                                    ->url(fn ($record) => $record->providerBranch ? route('filament.admin.resources.providers.edit', $record->providerBranch->provider->id) : null)
                                    ->suffixAction(
                                        InfolistAction::make('copy_provider_name')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->providerBranch->provider->name;
                                                $this->copyToClipboard($text, 'Provider Name');
                                            })
                                    ),
                                TextEntry::make('providerBranch.branch_name')
                                    ->label('Branch Name')
                                    ->color('info')
                                    ->formatStateUsing(function ($state, $record) {
                                        $branchName = $state;
                                        $dayCost = $record->providerBranch->day_cost ?? null;
                                        if ($dayCost) {
                                            return $branchName . ' (€' . number_format($dayCost, 2) . ')';
                                        }
                                        return $branchName;
                                    })
                                    ->url(fn ($record) => $record->providerBranch ? route('filament.admin.resources.provider-branches.edit', $record->providerBranch->id) : null)
                                    ->suffixAction(
                                        InfolistAction::make('copy_branch_name')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->providerBranch->branch_name;
                                                $this->copyToClipboard($text, 'Branch Name');
                                            })
                                    ),
                                TextEntry::make('service_date')
                                    ->label('Service Date')
                                    ->date()
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_service_date')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->service_date;
                                                $this->copyToClipboard($text, 'Service Date');
                                            })
                                    ),
                                TextEntry::make('service_time')
                                    ->label('Service Time')
                                    ->time()
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_service_time')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->service_time;
                                                $this->copyToClipboard($text, 'Service Time');
                                            })
                                    ),
                            ])
                            ->columnSpan(1),

                        // Column 3: Location & Medical Info (Condensed)
                        Card::make()
                            ->schema([
                                TextEntry::make('country.name')
                                    ->label('Country')
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_country')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->country->name;
                                                $this->copyToClipboard($text, 'Country');
                                            })
                                    ),
                                TextEntry::make('city.name')
                                    ->label('City')
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_city')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->city->name;
                                                $this->copyToClipboard($text, 'City');
                                            })
                                    ),
                                TextEntry::make('address')
                                    ->label('Address')
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_address')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->address;
                                                $this->copyToClipboard($text, 'Address');
                                            })
                                    ),
                                TextEntry::make('symptoms')
                                    ->label('Symptoms')
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_symptoms')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->symptoms;
                                                $this->copyToClipboard($text, 'Symptoms');
                                            })
                                    ),
                                TextEntry::make('diagnosis')
                                    ->label('Diagnosis')
                                    ->color('info')
                                    ->suffixAction(
                                        InfolistAction::make('copy_diagnosis')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->diagnosis;
                                                $this->copyToClipboard($text, 'Diagnosis');
                                            })
                                    ),
                                TextEntry::make('google_drive_link')
                                    ->label('Google Drive Link')
                                    ->color('info')
                                    ->formatStateUsing(fn ($state) => $state)
                                    ->url(fn ($state) => str_starts_with($state, 'http') ? $state : "https://{$state}", true)
                                    ->suffixAction(
                                        InfolistAction::make('copy_google_drive_link')
                                            ->icon('heroicon-o-clipboard-document')
                                            ->color('gray')
                                            ->action(function ($record) {
                                                $text = $record->google_drive_link;
                                                $this->copyToClipboard($text, 'Google Drive Link');
                                            })
                                    ),
                            ])
                            ->columnSpan(1),
                                    ]),

                            // Current Text (Below the 3-column grid)
                            InfolistSection::make()
                                ->schema([
                                    Card::make()
                                        ->schema([
                                            HtmlEntry::make('current_text')
                                                ->label('Current Text')
                                                ->state(function ($record) {
                                                    $text = $this->formatCaseInfo($record);
                                                    return '<pre class="whitespace-pre-wrap font-mono text-sm">' . htmlspecialchars($text) . '</pre>';
                                                })
                                                ->suffixAction(
                                                    InfolistAction::make('copy_current_text')
                                                        ->icon('heroicon-o-clipboard-document')
                                                        ->color('gray')
                                                        ->action(function ($record) {
                                                            $text = $this->formatCaseInfo($record);
                                                            $this->copyToClipboard($text, 'Current Text');
                                                        })
                                                ),
                                        ]),
                                ]),
                            ]),
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
        return [
            Action::make('requestAppointment')
                ->label('Request Appointment')
                ->icon('heroicon-o-globe-alt')
                ->color('primary')
                ->slideOver()
                ->modalHeading('Request Appointment - Select Provider Branches')
                ->modalDescription('Choose which provider branches to send appointment requests to. Branches are filtered by city, service type, and active status, sorted by priority.')
                ->modalWidth('7xl')
                ->form([
                    Section::make('Available Branches')
                        ->description('Select the provider branches you want to send appointment requests to')
                        ->schema([
                            // Table-like header
                            Grid::make(11)
                                ->schema([
                                    Checkbox::make('select_all_branches')
                                        ->label('')
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            $branches = $this->getEligibleProviderBranches($this->record);
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
                                    \Filament\Forms\Components\Placeholder::make('header_provider')
                                        ->label('Provider')
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
                                    \Filament\Forms\Components\Placeholder::make('header_distance')
                                        ->label('Distance')
                                        ->content('')
                                        ->columnSpan(2),
                                ])
                                ->extraAttributes(['class' => 'bg-gray-50 border-b-2 border-gray-200 font-semibold text-sm']),
                            
                            // Branch rows
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
            Action::make('notifyClient')
                ->label('Notify Client')
                ->slideOver()
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->modalHeading('Notify Client')
                ->modalWidth('7xl')
                ->form([
                    Select::make('draft_mail_id')
                        ->label('Select Template')
                        ->options(function () {
                            return DraftMail::where('type', 'file')
                                ->pluck('mail_name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $record, $get) {
                            Log::info('Template selected', ['draft_mail_id' => $state]);
                            if ($state) {
                                $draftMail = DraftMail::find($state);
                                if ($draftMail) {
                                    $this->updatePreview($set, $record, $get);
                                }
                            }
                        }),
                    
                    CheckboxList::make('include_fields')
                        ->label('Include Optional Fields')
                        ->options([
                            'patient_name' => 'Patient Name',
                            'service_type' => 'Service Type',
                            'country' => 'Country',
                            'city' => 'City',
                            'provider_branch' => 'Provider Branch',
                            'provider_name' => 'Provider Name',
                        ])
                        ->columns(3)
                        ->default(['patient_name', 'service_type', 'diagnosis'])
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $record, $get) {
                            Log::info('Checkbox state updated', ['state' => $state]);
                            $this->updatePreview($set, $record, $get);
                        }),
                    
                    Textarea::make('custom_notes')
                        ->label('Custom Notes')
                        ->placeholder('Add any additional notes to append to the message...')
                        ->rows(3)
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $record, $get) {
                            Log::info('Custom notes updated', ['notes' => $state]);
                            $this->updatePreview($set, $record, $get);
                        }),
                    
                    Textarea::make('preview_content')
                        ->label('Message Preview')
                        ->rows(10)
                        ->disabled()
                        ->placeholder('Select a template to see the preview...')
                        ->columnSpanFull(),
                    

                ])
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    \Filament\Actions\Action::make('translate_spanish')
                        ->label('Spanish')
                        ->icon('heroicon-o-language')
                        ->color(Color::Blue)
                        ->modalHeading('Translated Message (Spanish)')
                        ->modalContent(function ($record) {
                            // For now, show a simple notification with translation info
                            return view('filament.forms.components.translated-message', [
                                'translatedMessage' => 'Please use the copy button to copy the current message, then translate it using an external translation service.',
                                'languageName' => 'Spanish'
                            ]);
                        })
                        ->modalActions([
                            \Filament\Actions\Action::make('copy_spanish')
                                ->label('Copy Current Message')
                                ->color(Color::Gray)
                                ->action(function ($record) {
                                    // Copy the current preview content
                                    $this->copyToClipboard('Current message copied. Please translate externally.', 'Current Message');
                                }),
                        ]),
                    
                    \Filament\Actions\Action::make('translate_italian')
                        ->label('Italian')
                        ->icon('heroicon-o-language')
                        ->color(Color::Green)
                        ->modalHeading('Translated Message (Italian)')
                        ->modalContent(function ($record) {
                            // For now, show a simple notification with translation info
                            return view('filament.forms.components.translated-message', [
                                'translatedMessage' => 'Please use the copy button to copy the current message, then translate it using an external translation service.',
                                'languageName' => 'Italian'
                            ]);
                        })
                        ->modalActions([
                            \Filament\Actions\Action::make('copy_italian')
                                ->label('Copy Current Message')
                                ->color(Color::Gray)
                                ->action(function ($record) {
                                    // Copy the current preview content
                                    $this->copyToClipboard('Current message copied. Please translate externally.', 'Current Message');
                                }),
                        ]),
                    
                    \Filament\Actions\Action::make('translate_german')
                        ->label('German')
                        ->icon('heroicon-o-language')
                        ->color(Color::Orange)
                        ->modalHeading('Translated Message (German)')
                        ->modalContent(function ($record) {
                            // For now, show a simple notification with translation info
                            return view('filament.forms.components.translated-message', [
                                'translatedMessage' => 'Please use the copy button to copy the current message, then translate it using an external translation service.',
                                'languageName' => 'German'
                            ]);
                        })
                        ->modalActions([
                            \Filament\Actions\Action::make('copy_german')
                                ->label('Copy Current Message')
                                ->color(Color::Gray)
                                ->action(function ($record) {
                                    // Copy the current preview content
                                    $this->copyToClipboard('Current message copied. Please translate externally.', 'Current Message');
                                }),
                        ]),
                    
                    \Filament\Actions\Action::make('translate_french')
                        ->label('French')
                        ->icon('heroicon-o-language')
                        ->color(Color::Purple)
                        ->modalHeading('Translated Message (French)')
                        ->modalContent(function ($record) {
                            // For now, show a simple notification with translation info
                            return view('filament.forms.components.translated-message', [
                                'translatedMessage' => 'Please use the copy button to copy the current message, then translate it using an external translation service.',
                                'languageName' => 'French'
                            ]);
                        })
                        ->modalActions([
                            \Filament\Actions\Action::make('copy_french')
                                ->label('Copy Current Message')
                                ->color(Color::Gray)
                                ->action(function ($record) {
                                    // Copy the current preview content
                                    $this->copyToClipboard('Current message copied. Please translate externally.', 'Current Message');
                                }),
                        ]),
                    
                    \Filament\Actions\Action::make('copy_to_clipboard')
                        ->label('Copy Current Message')
                        ->icon('heroicon-o-clipboard-document')
                        ->color(Color::Gray)
                        ->action(function ($record) {
                            // Get the current preview content from the form
                            $previewContent = request()->input('preview_content') ?? 'No message available';
                            $this->copyToClipboard($previewContent, 'Current Message');
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
            Action::make('Update File')
                ->label('Update File')
                ->icon('heroicon-o-pencil')
                ->url(fn ($record) => route('filament.admin.resources.files.edit', $record))
                ->openUrlInNewTab(false)
        ];
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

    /**
     * Get eligible provider branches for a file
     */
    protected function getEligibleProviderBranches($record)
    {
        // Use the File model's availableBranches method for consistent filtering
        $availableBranches = $record->availableBranches();
        
        // Get the most relevant branches (city branches first, then all branches)
        $branchIds = collect();
        
        if (isset($availableBranches['cityBranches'])) {
            $branchIds = $branchIds->merge($availableBranches['cityBranches']->pluck('id'));
        }
        
        if (isset($availableBranches['allBranches'])) {
            $branchIds = $branchIds->merge($availableBranches['allBranches']->pluck('id'));
        }
        
        // Remove duplicates and fetch with proper relationships
        $uniqueIds = $branchIds->unique()->values()->toArray();
        
        if (empty($uniqueIds)) {
            return collect();
        }
        
        return \App\Models\ProviderBranch::whereIn('id', $uniqueIds)
            ->with(['provider', 'city', 'services', 'gopContact', 'operationContact'])
            ->orderBy('priority', 'asc')
            ->get();
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
        if (!$this->record || !$this->record->address) {
            return 'No file address';
        }

        if (!$branch->address) {
            return 'No branch address';
        }

        try {
            $distanceService = new \App\Services\DistanceCalculationService();
            
            // Calculate driving distance
            $drivingDistance = $distanceService->calculateDistance(
                $this->record->address,
                $branch->address,
                'driving'
            );
            
            // Calculate walking distance
            $walkingDistance = $distanceService->calculateDistance(
                $this->record->address,
                $branch->address,
                'walking'
            );

            $result = [];
            
            if ($drivingDistance) {
                $result[] = "🚗 " . $drivingDistance['duration'] . " (" . $drivingDistance['distance'] . ")";
            }
            
            if ($walkingDistance) {
                $result[] = "🚶 " . $walkingDistance['duration'] . " (" . $walkingDistance['distance'] . ")";
            }

            if (empty($result)) {
                return 'Distance unavailable';
            }

            return '<ul class="list-disc list-inside space-y-1">' . 
                   implode('', array_map(fn($item) => '<li>' . $item . '</li>', $result)) . 
                   '</ul>';
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Distance calculation error', [
                'error' => $e->getMessage(),
                'branch_id' => $branch->id,
                'file_id' => $this->record->id
            ]);
            return 'Distance calculation failed';
        }
    }

    /**
     * Get branch rows for table-like display
     */
    protected function getBranchRows(): array
    {
        $branches = $this->getEligibleProviderBranches($this->record);
        $rows = [];
        
        foreach ($branches as $branch) {
            $rows[] = Grid::make(11)
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
                            
                            // Update "Select All" checkbox state
                            $branches = $this->getEligibleProviderBranches($this->record);
                            $totalBranches = $branches->count();
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
                    
                    // Branch name column
                    \Filament\Forms\Components\View::make('branch_name_' . $branch->id)
                        ->view('filament.forms.components.branch-name-link')
                        ->viewData([
                            'branchName' => $branch->branch_name,
                            'branchId' => $branch->id
                        ])
                        ->columnSpan(2),
                    
                    // Provider column
                    \Filament\Forms\Components\View::make('provider_' . $branch->id)
                        ->view('filament.forms.components.provider-name-link')
                        ->viewData([
                            'providerName' => $branch->provider->name ?? 'N/A',
                            'providerId' => $branch->provider->id ?? null
                        ])
                        ->columnSpan(2),
                    
                    // Priority column
                    \Filament\Forms\Components\Placeholder::make("priority_{$branch->id}")
                        ->label('')
                        ->content($branch->priority ?? 'N/A')
                        ->extraAttributes(['class' => 'text-sm leading-tight'])
                        ->columnSpan(1),
                    
                    // Cost column
                    \Filament\Forms\Components\Placeholder::make("cost_{$branch->id}")
                        ->label('')
                        ->content(function () use ($branch) {
                            if ($this->record && $this->record->service_type_id) {
                                $service = $branch->services()
                                    ->where('service_type_id', $this->record->service_type_id)
                                    ->first();
                                if ($service) {
                                    $minCost = $service->pivot->min_cost;
                                    $maxCost = $service->pivot->max_cost;
                                    
                                    if ($minCost && $maxCost) {
                                        if ($minCost == $maxCost) {
                                            return '€' . number_format($minCost, 2);
                                        } else {
                                            return '€' . number_format($minCost, 2) . ' - €' . number_format($maxCost, 2);
                                        }
                                    } elseif ($minCost) {
                                        return '€' . number_format($minCost, 2);
                                    } elseif ($maxCost) {
                                        return '€' . number_format($maxCost, 2);
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
                    
                    // Distance column
                    \Filament\Forms\Components\View::make('distance_' . $branch->id)
                        ->view('filament.forms.components.distance-info')
                        ->viewData([
                            'distanceInfo' => $this->getBranchDistanceInfo($branch)
                        ])
                        ->columnSpan(2),
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
