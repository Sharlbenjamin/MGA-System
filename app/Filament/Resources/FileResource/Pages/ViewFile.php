<?php

namespace App\Filament\Resources\FileResource\Pages;

use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use App\Filament\Resources\FileResource;
use Filament\Infolists\Components\Card;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\View;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentRequestMail;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Filament\Widgets\CommentsWidget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Infolists\Components\Actions\Action as InfolistAction;

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
            ->schema([
                // Main content with condensed layout
                Section::make()
                    ->columns(3) // Three columns for more condensed layout
                    ->schema([
                        // Column 1: Patient & Client Info (Condensed)
                        Section::make()->schema([
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
                                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->age . ' years')
                                        ->suffixAction(
                                            InfolistAction::make('copy_patient_age')
                                                ->icon('heroicon-o-clipboard-document')
                                                ->color('gray')
                                                ->action(function ($record) {
                                                    $text = \Carbon\Carbon::parse($record->patient->dob)->age . ' years';
                                                    $this->copyToClipboard($text, 'Patient Age');
                                                })
                                        ),
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
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
                ->modalWidth('xl')
                ->form([
                    Select::make('status')->searchable()
                        ->options(['New' => 'New',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                        'Requesting GOP' => 'Requesting GOP',
                        'Ask' => 'Ask',
                        'Custom' => 'Custom'
                        ])->required()->live(),
                    Textarea::make('message')->visible(fn ($get) => $get('status') == 'Custom')
                ])->modalSubmitActionLabel('Send')
                ->action(function (array $data, $record) {
                    $message = $data['status'] === 'Custom' ? ($data['message'] ?? null) : null;
                    $record->patient->client->notifyClient($data['status'], $record, $message);
                }),
            Action::make('viewFinancial')
                ->label('Invocies & Bills')
                ->icon('heroicon-o-document-currency-euro')
                ->url(fn ($record) => route('filament.admin.resources.patients.financial', [
                    'record' => $record->patient_id,
                    'file_id' => $record->id
                ]))
                ->openUrlInNewTab(false)->color('success'),
            Action::make('requestAppointments')
                ->label('Request Appointments')
                ->modalHeading('Select Branches for Appointment Request')
                ->modalWidth('4xl')
                ->form([
                    Toggle::make('searchByProvince')
                        ->label('Search by Province')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $record) {
                            $branches = $record->availableBranches();
                            $selectedBranches = $state ? $branches['allBranches'] : $branches['cityBranches'];

                            $set('selected_branches', $selectedBranches->map(fn ($branch) => [
                                'id' => $branch->id,
                                'selected' => false,
                                'name' => $branch->branch_name,
                                'provider' => $branch->provider->name ?? 'N/A',
                                'preferred_contact' => optional($branch->primaryContact('Appointment'))->preferred_contact ?? 'N/A',
                            ])->toArray());
                        }),
                    Repeater::make('selected_branches')
                        ->label('Available Branches')
                        ->schema([
                            Checkbox::make('selected')->label('Select')->default(false),
                            TextInput::make('name')->label('Branch Name')->disabled(),
                            TextInput::make('provider')->label('Provider Name')->disabled(),
                            TextInput::make('preferred_contact')->label('Preferred Contact')->default(fn ($get) => optional($get('contact'))->preferred_contact ?? 'N/A')->disabled(),
                        ])
                        ->columns(4)
                        ->default(function ($get, $record) {
                            $branches = $record->availableBranches();
                            $selectedBranches = $branches['cityBranches']; // Start with city branches

                            return $selectedBranches->map(fn ($branch) => [
                                'id' => $branch->id,
                                'selected' => false,
                                'name' => $branch->branch_name,
                                'provider' => $branch->provider->name ?? 'N/A',
                                'preferred_contact' => optional($branch->primaryContact('Appointment'))->preferred_contact ?? 'N/A',
                            ])->toArray();
                        }),
                ])
                ->modalButton('Send Requests')
                ->action(fn (array $data, $record) => $this->bulkSendRequests($data, $record)),

            Action::make('Update Request')
                ->label('Update Request')
                ->icon('heroicon-o-pencil')
                ->url(fn ($record) => route('filament.admin.resources.files.edit', $record))
                ->openUrlInNewTab(false)
        ];
    }

    public function bulkSendRequests(array $data, $record)
    {
        $selectedBranches = collect($data['selected_branches'] ?? [])
            ->filter(fn ($branch) => $branch['selected'])
            ->pluck('id');

        $skippedBranches = [];
        $updatedAppointments = [];
        $newAppointments = [];

        DB::transaction(function () use ($selectedBranches, $record, &$skippedBranches, &$updatedAppointments, &$newAppointments) {
            foreach ($selectedBranches as $branchId) {
                $providerBranch = \App\Models\ProviderBranch::find($branchId);

                if (!$providerBranch) {
                    continue;
                }

                $contact = $providerBranch->primaryContact('Appointment');
                if (!$contact) {
                    $skippedBranches[] = $providerBranch->branch_name;
                    continue;
                }

                // ✅ Check if an appointment already exists
                $existingAppointment = $record->appointments()
                    ->where('provider_branch_id', $branchId)
                    ->first();

                if ($existingAppointment) {
                    $newDate = now()->toDateString();

                    if ($existingAppointment->service_date !== $newDate) {
                        $existingAppointment->update([
                            'service_date' => $newDate,
                        ]);
                        $updatedAppointments[] = $providerBranch->branch_name;
                    }
                    continue;
                }

                // ✅ Ensure a new appointment is created
                $appointment = new \App\Models\Appointment([
                    'file_id' => $record->id,
                    'provider_branch_id' => $branchId,
                    'service_date' => now()->toDateString(),
                    'service_time' => now()->toTimeString(),
                    'status' => 'Requested',
                ]);

                if ($appointment->save()) {
                    // ✅ Track newly created appointments
                    $newAppointments[] = $providerBranch->branch_name;

                    // ✅ Create a task for the new appointment
                    \App\Models\Task::create([
                        'taskable_id' => $appointment->id,
                        'taskable_type' => \App\Models\Appointment::class,
                        'department' => 'Operation',
                        'title' => 'New Appointment Request',
                        'description' => "Confirm appointment with {$providerBranch->branch_name} for {$appointment->service_date}.",
                        'due_date' => now()->addHours(2),
                        'user_id' => Auth::id(),
                        'file_id' => $record->id,
                    ]);
                }
            }
        });

        // ✅ Notify user of results
        if (!empty($skippedBranches)) {
            Notification::make()->title('Some Requests Skipped')->body('The following branches were skipped due to missing contact: ' . implode(', ', $skippedBranches))->warning()->send();
        }

        if (!empty($updatedAppointments)) {
            Notification::make()->title('Appointments Updated')->body('Appointments updated for: ' . implode(', ', $updatedAppointments))->info()->send();
        }

        if (!empty($newAppointments)) {
            Notification::make()->title('Appointments Created')->body('Appointments created for: ' . implode(', ', $newAppointments))->success()->send();
        }
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
                var textToCopy = " . $escapedText . ";
                
                // Try modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        console.log('Text copied successfully (modern API)');
                    }).catch(function(err) {
                        console.error('Modern clipboard API failed:', err);
                        fallbackCopy();
                    });
                } else {
                    fallbackCopy();
                }
                
                function fallbackCopy() {
                    var textArea = document.createElement('textarea');
                    textArea.value = textToCopy;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-9999px';
                    textArea.style.top = '-9999px';
                    textArea.style.opacity = '0';
                    textArea.style.pointerEvents = 'none';
                    
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    
                    try {
                        var successful = document.execCommand('copy');
                        if (successful) {
                            console.log('Text copied successfully (fallback)');
                        } else {
                            console.error('execCommand copy failed');
                            alert('Copy failed. Please manually select and copy the text.');
                        }
                    } catch (err) {
                        console.error('execCommand copy error:', err);
                        alert('Copy failed. Please manually select and copy the text.');
                    }
                    
                    document.body.removeChild(textArea);
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
        
        // Return formatted string with proper line breaks
        return "Patient Name: {$patientName}\nDOB: {$dob}\nMGA Reference: {$mgaReference}\nSymptoms: {$symptoms}\nRequest: {$request}";
    }
}
