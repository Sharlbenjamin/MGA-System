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
use Illuminate\Support\Str;
use App\Models\DraftMail;
use Filament\Forms\Components\RichEditor;
use App\Models\Task;

use Filament\Support\Colors\Color;

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
                                'day_cost' => $branch->day_cost ? '€' . number_format($branch->day_cost, 2) : 'N/A',
                                'preferred_contact' => $this->getPreferredContactDisplay($branch),
                            ])->toArray());
                        }),
                    
                    Repeater::make('selected_branches')
                        ->label('Available Branches')
                        ->schema([
                            Checkbox::make('selected')->label('Select')->default(false),
                            TextInput::make('name')->label('Branch Name')->disabled(),
                            TextInput::make('provider')->label('Provider Name')->disabled(),
                            TextInput::make('day_cost')->label('Day Cost (€)')->disabled(),
                            TextInput::make('preferred_contact')->label('Preferred Contact')->default(fn ($get) => optional($get('contact'))->preferred_contact ?? 'N/A')->disabled(),
                        ])
                        ->columns(5)
                        ->default(function ($get, $record) {
                            $branches = $record->availableBranches();
                            $selectedBranches = $branches['cityBranches']; // Start with city branches

                            return $selectedBranches->map(fn ($branch) => [
                                'id' => $branch->id,
                                'selected' => false,
                                'name' => $branch->branch_name,
                                'provider' => $branch->provider->name ?? 'N/A',
                                'day_cost' => $branch->day_cost ? '€' . number_format($branch->day_cost, 2) : 'N/A',
                                'preferred_contact' => $this->getPreferredContactDisplay($branch),
                            ])->toArray();
                        })
                        ->addActionLabel('Add More Branches')
                        ->addAction(function ($get, $set, $record) {
                            // This will be handled by the custom action
                        }),
                    
                    Repeater::make('custom_emails')
                        ->label('Custom Email Addresses')
                        ->schema([
                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->placeholder('Enter email address'),
                        ])
                        ->columns(1)
                        ->default([])
                        ->addActionLabel('Add Custom Email')
                        ->reorderable(false)
                        ->collapsible()
                        ->collapsed(),
                ])
                ->modalButton('Send Requests')
                ->action(fn (array $data, $record) => $this->bulkSendRequests($data, $record)),

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

    public function bulkSendRequests(array $data, $record)
    {
        $selectedBranches = collect($data['selected_branches'] ?? [])
            ->filter(fn ($branch) => $branch['selected'])
            ->pluck('id')
            ->toArray();

        $customEmails = collect($data['custom_emails'] ?? [])
            ->pluck('email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->toArray();

        // If no branches or custom emails selected, show warning
        if (empty($selectedBranches) && empty($customEmails)) {
            Notification::make()
                ->title('No Recipients Selected')
                ->body('Please select at least one provider branch or add a custom email address.')
                ->warning()
                ->send();
            return;
        }

        // Send notifications and emails
        $successfulBranches = [];
        $skippedBranches = [];
        $updatedAppointments = [];
        $newAppointments = [];

        // Create/update appointments in a transaction
        DB::transaction(function () use ($selectedBranches, $record, &$updatedAppointments, &$newAppointments) {
            foreach ($selectedBranches as $branchId) {
                $providerBranch = \App\Models\ProviderBranch::find($branchId);
                
                if (!$providerBranch) {
                    continue;
                }

                // Check if an appointment already exists
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
                    // Note: We continue here to avoid creating duplicate appointments
                    // but we still send emails in the notification loop below
                    continue;
                }

                // Create new appointment
                $appointment = new \App\Models\Appointment([
                    'file_id' => $record->id,
                    'provider_branch_id' => $branchId,
                    'service_date' => now()->toDateString(),
                    'service_time' => now()->toTimeString(),
                    'status' => 'Requested',
                ]);

                if ($appointment->save()) {
                    $newAppointments[] = $providerBranch->branch_name;

                    // Create a task for the new appointment
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

        // Process provider branch notifications
        foreach ($selectedBranches as $branchId) {
            $providerBranch = \App\Models\ProviderBranch::find($branchId);
            
            if (!$providerBranch) {
                continue;
            }

            // Get the operation contact for the branch
            $operationContact = $providerBranch->operationContact();
            
            if (!$operationContact) {
                $skippedBranches[] = $providerBranch->branch_name . ' (No operation contact)';
                continue;
            }

            // Find or create the appointment for this branch
            $appointment = $record->appointments()
                ->where('provider_branch_id', $branchId)
                ->first();

            if (!$appointment) {
                // Create a new appointment if none exists
                $appointment = new \App\Models\Appointment([
                    'file_id' => $record->id,
                    'provider_branch_id' => $branchId,
                    'service_date' => now()->toDateString(),
                    'service_time' => now()->toTimeString(),
                    'status' => 'Requested',
                ]);
                $appointment->save();
            }

            // Always send notifications when "Request Appointment" is clicked
            // This allows users to resend appointment requests as needed
            try {
                $providerBranch->notifyBranch('appointment_created', $appointment);
                $successfulBranches[] = $providerBranch->branch_name . ' (Notification sent)';
            } catch (\Exception $e) {
                $skippedBranches[] = $providerBranch->branch_name . ' (Notification failed: ' . $e->getMessage() . ')';
            }
        }

        // Send to custom emails
        foreach ($customEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    // Create a proper appointment object with a dummy branch for custom emails
                    // This ensures the same template is used as branch emails
                    $tempAppointment = new \App\Models\Appointment([
                        'file_id' => $record->id,
                        'provider_branch_id' => null, // Will be set below
                        'service_date' => now()->toDateString(),
                        'service_time' => now()->toTimeString(),
                        'status' => 'Requested',
                    ]);
                    
                    // Create a dummy branch object for the template
                    $dummyBranch = new \App\Models\ProviderBranch([
                        'id' => 0,
                        'branch_name' => 'Custom Provider',
                        'provider_id' => 0,
                    ]);
                    
                    // Create a dummy provider for the branch
                    $dummyProvider = new \App\Models\Provider([
                        'id' => 0,
                        'name' => 'Custom Provider',
                        'status' => 'Inactive', // This will trigger the @else condition in the template
                    ]);
                    
                    // Set the relationships manually
                    $dummyBranch->setRelation('provider', $dummyProvider);
                    $tempAppointment->setRelation('providerBranch', $dummyBranch);
                    
                    // Use the exact same mailable as branches
                    $mailable = new \App\Mail\NotifyBranchMailable('appointment_created', $tempAppointment);
                    
                    Mail::to($email)->send($mailable);
                    
                    $successfulBranches[] = "Custom: {$email}";
                } catch (\Exception $e) {
                    $skippedBranches[] = "Custom: {$email} (Email failed: " . $e->getMessage() . ")";
                }
            }
        }

        // Notify the user who created the request using Filament notifications
        if (Auth::check()) {
            $user = Auth::user();
            $title = 'Appointment Requests Sent';
            $body = "Successfully sent appointment request emails for file {$record->mga_reference}";
            
            if (!empty($skippedBranches)) {
                $body .= "\n\nSome requests failed: " . implode(', ', $skippedBranches);
            }

            $notification = \Filament\Notifications\Notification::make()
                ->title($title)
                ->body($body);

            // Set notification type based on results
            if (empty($skippedBranches)) {
                $notification->success();
            } elseif (empty($successfulBranches)) {
                $notification->danger();
            } else {
                $notification->warning();
            }

            Log::info('Sending Filament notification for appointment request', [
                'user_id' => $user->id,
                'file_id' => $record->id,
                'title' => $title,
            ]);
            $notification->send(); // popup
            $notification->sendToDatabase($user); // persistent

            // Workaround for Filament v3.3.0 bug: manually set notification as unread
            try {
                $latestNotification = $user->notifications
                    ->where('type', 'Filament\Notifications\DatabaseNotification')
                    ->sortByDesc('created_at')
                    ->first();
                
                if ($latestNotification && $latestNotification->read_at) {
                    $latestNotification->update(['read_at' => null]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to fix notification read status', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'file_id' => $record->id
                ]);
            }
        }

        // Show immediate feedback notifications
        if (!empty($skippedBranches)) {
            Notification::make()
                ->title('Some Requests Skipped')
                ->body('The following recipients were skipped: ' . implode(', ', $skippedBranches))
                ->warning()
                ->send();
        }

        if (!empty($updatedAppointments)) {
            Notification::make()
                ->title('Appointments Updated')
                ->body('Appointments updated for: ' . implode(', ', $updatedAppointments))
                ->info()
                ->send();
        }

        if (!empty($newAppointments)) {
            Notification::make()
                ->title('Appointments Created')
                ->body('Appointments created for: ' . implode(', ', $newAppointments))
                ->success()
                ->send();
        }

        if (!empty($successfulBranches)) {
            Notification::make()
                ->title('Notifications Sent')
                ->body("Successfully sent notifications to: " . implode(', ', $successfulBranches))
                ->success()
                ->send();
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
        
        // Return formatted string with proper line breaks
        return "Patient Name: {$patientName}\nDOB: {$dob}\nMGA Reference: {$mgaReference}\nSymptoms: {$symptoms}\nRequest: {$request}";
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
            'description' => "Call {$providerBranch->branch_name} to confirm appointment. Contact: {$gopContact->name} - {$gopContact->phone_number}. File: {$record->mga_reference}",
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
}
