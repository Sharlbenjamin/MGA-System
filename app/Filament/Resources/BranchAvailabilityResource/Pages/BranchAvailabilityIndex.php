<?php

namespace App\Filament\Resources\BranchAvailabilityResource\Pages;

use App\Filament\Resources\BranchAvailabilityResource;
use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Mail\AppointmentRequestMailable;
use App\Services\DistanceCalculationService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BranchAvailabilityIndex extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = BranchAvailabilityResource::class;
    protected static string $view = 'filament.resources.branch-availability-resource.pages.branch-availability-index';

    public ?array $data = [];
    public ?int $selectedFileId = null;
    public ?File $selectedFile = null;

    public function mount(): void
    {
        // Check if a file ID was passed in the URL
        $fileId = request()->query('file');
        if ($fileId) {
            $file = File::with(['patient.client', 'serviceType', 'country', 'city'])->find($fileId);
            if ($file) {
                $this->selectedFile = $file;
                $this->selectedFileId = (int) $fileId;
                
                // Pre-fill the form with the selected file
                $this->form->fill([
                    'selectedFileId' => $fileId,
                    'customEmails' => []
                ]);
                
                // Show success notification
                Notification::make()
                    ->title('File Pre-selected')
                    ->body("File {$file->mga_reference} has been automatically selected.")
                    ->success()
                    ->send();
            }
        } else {
            $this->form->fill();
        }
    }

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('File Selection')
                    ->description('Choose an MGA file to view details and send appointment requests')
                    ->schema([
                        Select::make('selectedFileId')
                            ->label('Select MGA File')
                            ->searchable()
                            ->options(function () {
                                return File::with(['patient.client', 'serviceType', 'country', 'city'])
                                    ->get()
                                    ->mapWithKeys(function ($file) {
                                        $label = $file->mga_reference . ' - ' . $file->patient->name . ' (' . $file->patient->client->company_name . ')';
                                        return [$file->id => $label];
                                    });
                            })
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $this->selectedFile = File::with(['patient.client', 'serviceType', 'country', 'city'])->find($state);
                                    $this->selectedFileId = (int) $state;
                                } else {
                                    $this->selectedFile = null;
                                    $this->selectedFileId = null;
                                }
                            })
                            ->helperText('Select a file to view its details below'),
                    ])
                    ->collapsible(),

                Section::make('Custom Email Recipients')
                    ->description('Add additional email addresses to receive the appointment request')
                    ->schema([
                        Repeater::make('customEmails')
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

                        Actions::make([
                            Action::make('sendRequest')
                                ->label('Send Appointment Request')
                                ->color('primary')
                                ->icon('heroicon-o-paper-airplane')
                                ->action('sendAppointmentRequest')
                                ->requiresConfirmation()
                                ->modalHeading('Send Appointment Request')
                                ->modalDescription('Are you sure you want to send appointment request emails to all provider branches based on the selected file?')
                                ->visible(fn (): bool => $this->selectedFileId !== null),
                        ])
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProviderBranch::query()->with(['provider', 'operationContact', 'branchServices.serviceType'])->where('status', 'Active'))
            ->columns([
                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (ProviderBranch $record): string => 
                        route('filament.admin.resources.provider-branches.edit', $record)
                    )
                    ->color('primary')
                    ->weight('medium'),

                TextColumn::make('provider.name')
                    ->label('Provider')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state <= 3 => 'success',
                        $state <= 6 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('available_services')
                    ->label('Available Services')
                    ->getStateUsing(function (ProviderBranch $record): string {
                        $services = [];
                        $serviceFields = [
                            'emergency' => 'Emergency',
                            'pediatrician_emergency' => 'Pediatric Emergency',
                            'dental' => 'Dental',
                            'pediatrician' => 'Pediatrician',
                            'gynecology' => 'Gynecology',
                            'urology' => 'Urology',
                            'cardiology' => 'Cardiology',
                            'ophthalmology' => 'Ophthalmology',
                            'trauma_orthopedics' => 'Trauma/Orthopedics',
                            'surgery' => 'Surgery',
                            'intensive_care' => 'Intensive Care',
                            'obstetrics_delivery' => 'Obstetrics/Delivery',
                            'hyperbaric_chamber' => 'Hyperbaric Chamber',
                        ];

                        foreach ($serviceFields as $field => $label) {
                            if ($record->$field) {
                                $services[] = $label;
                            }
                        }

                        return empty($services) ? 'No services specified' : implode(', ', $services);
                    })
                    ->wrap()
                    ->limit(50),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Hold' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('cost_info')
                    ->label('Cost Information')
                    ->getStateUsing(function (ProviderBranch $record): string {
                        if (!$this->selectedFile || !$this->selectedFile->service_type_id) {
                            return 'Select file to view costs';
                        }

                        $costs = $record->getCostsForService($this->selectedFile->service_type_id);
                        if (!$costs) {
                            return 'No pricing available';
                        }

                        $costStrings = [];
                        if ($costs['day_cost']) $costStrings[] = "Day: {$costs['day_cost']}";
                        if ($costs['night_cost']) $costStrings[] = "Night: {$costs['night_cost']}";
                        if ($costs['weekend_cost']) $costStrings[] = "Weekend: {$costs['weekend_cost']}";
                        if ($costs['weekend_night_cost']) $costStrings[] = "Weekend Night: {$costs['weekend_night_cost']}";

                        return empty($costStrings) ? 'No costs specified' : implode(' | ', $costStrings);
                    })
                    ->wrap(),

                TextColumn::make('contact_info')
                    ->label('Contact Information')
                    ->getStateUsing(function (ProviderBranch $record): string {
                        $contactInfo = [];
                        
                        if ($record->phone) {
                            $contactInfo[] = "📞 {$record->phone}";
                        }
                        
                        if ($record->email) {
                            $contactInfo[] = "✉️ {$record->email}";
                        }

                        if ($record->operationContact) {
                            if ($record->operationContact->phone) {
                                $contactInfo[] = "📞 Op: {$record->operationContact->phone}";
                            }
                            if ($record->operationContact->email) {
                                $contactInfo[] = "✉️ Op: {$record->operationContact->email}";
                            }
                        }

                        return empty($contactInfo) ? 'No contact info' : implode(' | ', $contactInfo);
                    })
                    ->wrap(),

                TextColumn::make('distance_info')
                    ->label('Distance & Travel Time')
                    ->getStateUsing(function (ProviderBranch $record): string {
                        if (!$this->selectedFile || !$this->selectedFile->address) {
                            return 'Select file with address';
                        }

                        try {
                            // Calculate distance by car
                            $carDistance = $this->calculateDistanceToAddress($record, $this->selectedFile->address, 'driving');
                            
                            // Calculate distance by walking
                            $walkDistance = $this->calculateDistanceToAddress($record, $this->selectedFile->address, 'walking');

                            $result = [];
                            if ($carDistance) {
                                $result[] = "🚗 {$carDistance['duration']} ({$carDistance['distance']})";
                            }
                            if ($walkDistance) {
                                $result[] = "🚶 {$walkDistance['duration']} ({$walkDistance['distance']})";
                            }

                            return empty($result) ? 'Distance unavailable' : implode(' | ', $result);
                        } catch (\Exception $e) {
                            Log::error('Distance calculation error', [
                                'error' => $e->getMessage(),
                                'branch_id' => $record->id,
                                'file_id' => $this->selectedFile->id
                            ]);
                            return 'Distance calculation failed';
                        }
                    })
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                    ]),

                SelectFilter::make('priority')
                    ->options([
                        '1' => '1 (Highest)',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                        '5' => '5',
                        '6' => '6',
                        '7' => '7',
                        '8' => '8',
                        '9' => '9',
                        '10' => '10 (Lowest)',
                    ]),

                SelectFilter::make('service_type')
                    ->label('Compatible Service Type')
                    ->options(function () {
                        return ServiceType::pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('branchServices', function (Builder $query) use ($data) {
                                $query->where('service_type_id', $data['value'])
                                    ->where('is_active', true);
                            });
                        }
                        return $query;
                    }),
            ])
            ->bulkActions([
                BulkAction::make('sendAppointmentRequests')
                    ->label('Send Appointment Requests to Selected')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Send Appointment Requests')
                    ->modalDescription('Send appointment request emails to all selected provider branches.')
                    ->action(function (Collection $records) {
                        $this->sendBulkAppointmentRequests($records);
                    })
                    ->visible(fn (): bool => $this->selectedFileId !== null),
            ])
            ->defaultSort('priority')
            ->poll('30s');
    }

    private function calculateDistanceToAddress(ProviderBranch $branch, string $address, string $mode = 'driving'): ?array
    {
        $distanceService = new DistanceCalculationService();
        
        // Try branch address first, then operation contact address
        $branchAddress = $branch->address ?? $branch->operationContact?->address;
        
        if (!$branchAddress) {
            return null;
        }

        return $distanceService->calculateDistance($address, $branchAddress, $mode);
    }

    public function sendAppointmentRequest(): void
    {
        if (!$this->selectedFile) {
            Notification::make()
                ->title('No File Selected')
                ->body('Please select an MGA file before sending appointment requests.')
                ->danger()
                ->send();
            return;
        }

        $emailData = $this->form->getState()['customEmails'] ?? [];
        $emails = array_column($emailData, 'email');

        $sentCount = 0;
        $errorCount = 0;

        // Get all active provider branches
        $branches = ProviderBranch::where('status', 'Active')->get();

        foreach ($branches as $branch) {
            try {
                Mail::send(new AppointmentRequestMailable($this->selectedFile, $branch, $emails));
                $sentCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to send appointment request email', [
                    'branch_id' => $branch->id,
                    'file_id' => $this->selectedFile->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($sentCount > 0) {
            Notification::make()
                ->title('Appointment Requests Sent')
                ->body("Successfully sent {$sentCount} appointment request emails.")
                ->success()
                ->send();
        }

        if ($errorCount > 0) {
            Notification::make()
                ->title('Some Emails Failed')
                ->body("{$errorCount} emails failed to send. Check logs for details.")
                ->warning()
                ->send();
        }
    }

    public function sendBulkAppointmentRequests(Collection $branches): void
    {
        if (!$this->selectedFile) {
            Notification::make()
                ->title('No File Selected')
                ->body('Please select an MGA file before sending appointment requests.')
                ->danger()
                ->send();
            return;
        }

        $emailData = $this->form->getState()['customEmails'] ?? [];
        $emails = array_column($emailData, 'email');

        $sentCount = 0;
        $errorCount = 0;

        foreach ($branches as $branch) {
            try {
                Mail::send(new AppointmentRequestMailable($this->selectedFile, $branch, $emails));
                $sentCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to send bulk appointment request email', [
                    'branch_id' => $branch->id,
                    'file_id' => $this->selectedFile->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($sentCount > 0) {
            Notification::make()
                ->title('Bulk Appointment Requests Sent')
                ->body("Successfully sent {$sentCount} appointment request emails to selected branches.")
                ->success()
                ->send();
        }

        if ($errorCount > 0) {
            Notification::make()
                ->title('Some Emails Failed')
                ->body("{$errorCount} emails failed to send. Check logs for details.")
                ->warning()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return 'Branch Availability';
    }

    public function getHeading(): string
    {
        return 'Branch Availability & Appointment Requests';
    }

    public function getSubheading(): ?string
    {
        return 'Select an MGA file and send appointment requests to provider branches with distance calculations and contact information.';
    }
}