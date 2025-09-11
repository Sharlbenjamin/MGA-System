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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\View;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Models\City;
use App\Models\Country;
use App\Models\BranchCity;
use App\Models\BranchService;
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
    public ?int $selectedCountryId = null;

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
                
                // Apply default filters based on file data
                $this->applyFileBasedFilters();
                
                // Show success notification
                Notification::make()
                    ->title('File Pre-selected')
                    ->body("File {$file->mga_reference} has been automatically selected with relevant filters applied.")
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
                    ->description('Choose an MGA file to view details in the table below')
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
                                    $this->applyFileBasedFilters();
                                } else {
                                    $this->selectedFile = null;
                                    $this->selectedFileId = null;
                                    $this->clearFileBasedFilters();
                                }
                            })
                            ->helperText('Select a file to see branch availability with distance calculations'),

                        // File Details Section (visible when file is selected)
                        Section::make('📋 Selected File Details')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        View::make('mga_reference')
                                            ->view('filament.forms.components.mga-reference-link')
                                            ->viewData([
                                                'file' => $this->selectedFile,
                                                'url' => $this->selectedFile ? route('filament.admin.resources.files.view', ['record' => $this->selectedFile->id]) : null
                                            ]),

                                        Placeholder::make('patient_name')
                                            ->label('👤 Patient Name')
                                            ->content(fn (): string => $this->selectedFile?->patient?->name ?? 'No file selected'),

                                        Placeholder::make('client_name')
                                            ->label('🏢 Client Name')
                                            ->content(fn (): string => $this->selectedFile?->patient?->client?->company_name ?? 'No file selected'),

                                        Placeholder::make('service_type')
                                            ->label('🏥 Service Type')
                                            ->content(fn (): string => $this->selectedFile?->serviceType?->name ?? 'No file selected'),

                                        Placeholder::make('country')
                                            ->label('🌍 Country')
                                            ->content(fn (): string => $this->selectedFile?->country?->name ?? 'No file selected'),

                                        Placeholder::make('city')
                                            ->label('🏙️ City')
                                            ->content(fn (): string => $this->selectedFile?->city?->name ?? 'No file selected'),

                                        Placeholder::make('service_date')
                                            ->label('📅 Date')
                                            ->content(function (): string {
                                                if (!$this->selectedFile || !$this->selectedFile->service_date) {
                                                    return 'Not scheduled';
                                                }
                                                return \Carbon\Carbon::parse($this->selectedFile->service_date)->format('F j, Y');
                                            }),

                                        Placeholder::make('service_time')
                                            ->label('⏰ Time')
                                            ->content(function (): string {
                                                if (!$this->selectedFile || !$this->selectedFile->service_time) {
                                                    return 'Not scheduled';
                                                }
                                                return \Carbon\Carbon::parse($this->selectedFile->service_time)->format('g:i A');
                                            }),

                                        Placeholder::make('status')
                                            ->label('📊 Status')
                                            ->content(fn (): string => $this->selectedFile?->status ?? 'No file selected'),
                                    ]),

                                // Address and Symptoms in full width
                                Placeholder::make('address')
                                    ->label('📍 Address')
                                    ->content(fn (): string => $this->selectedFile?->address ?? 'No address specified')
                                    ->visible(fn (): bool => $this->selectedFile && $this->selectedFile->address),

                                Placeholder::make('symptoms')
                                    ->label('🩺 Symptoms')
                                    ->content(fn (): string => $this->selectedFile?->symptoms ?? 'No symptoms specified')
                                    ->visible(fn (): bool => $this->selectedFile && $this->selectedFile->symptoms),
                            ])
                            ->visible(fn (): bool => $this->selectedFile !== null)
                            ->compact(),
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function getFooterWidgets(): array
    {
        return [
            // This will display the email form at the bottom
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('configureEmails')
                ->label('Send CustomEmail')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->form([
                    Section::make('Email Configuration')
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
                        ]),
                ])
                ->action(function (array $data) {
                    $this->data['customEmails'] = $data['customEmails'] ?? [];
                })
                ->fillForm(fn () => ['customEmails' => $this->data['customEmails'] ?? []])
                ->slideOver(),

            \Filament\Actions\Action::make('sendToAll')
                ->label('Send to All Branches')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Send Appointment Requests')
                ->modalDescription('Send appointment request emails to all active provider branches?')
                ->action('sendAppointmentRequest')
                ->visible(fn (): bool => $this->selectedFileId !== null),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProviderBranch::query()->with(['provider', 'operationContact', 'services', 'cities'])->where('status', 'Active'))
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

                TextColumn::make('services')
                    ->label('Available Services')
                    ->getStateUsing(function (ProviderBranch $record): string {
                        $activeServices = $record->services()
                            ->get()
                            ->pluck('name')
                            ->filter()
                            ->toArray();

                        return empty($activeServices) ? 'No active services' : implode(', ', $activeServices);
                    })
                    ->wrap()
                    ->limit(50)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('services', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('costs')
                    ->label('Cost')
                    ->getStateUsing(function (ProviderBranch $record): string {
                        if (!$this->selectedFile || !$this->selectedFile->service_type_id) {
                            return 'Select file to view costs';
                        }

                        $costs = $record->getCostsForService($this->selectedFile->service_type_id);
                        if (!$costs) {
                            return 'No pricing available';
                        }

                        // Get all available costs and find the cheapest
                        $availableCosts = array_filter([
                            $costs['min_cost'],
                            $costs['max_cost']
                        ], function($cost) {
                            return $cost !== null && $cost > 0;
                        });

                        if (empty($availableCosts)) {
                            return 'No costs specified';
                        }

                        $cheapestCost = min($availableCosts);
                        return $cheapestCost . '€';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('id', $direction); // Simple fallback sort since costs are dynamic
                    }),

                TextColumn::make('contact_info')
                    ->label('Contact Information')
                    ->html()
                    ->wrap()
                    ->getStateUsing(function (ProviderBranch $record): string {
                        $contactMethods = [];
                        
                        // Get direct branch email
                        if ($record->email) {
                            $contactMethods[] = "<div class='flex items-center gap-1'>
                                <svg class='w-4 h-4 text-blue-500' fill='currentColor' viewBox='0 0 20 20'>
                                    <path d='M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z'></path>
                                    <path d='M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z'></path>
                                </svg>
                                <a href='mailto:{$record->email}' class='text-blue-600 hover:text-blue-800 underline'>Email</a>
                            </div>";
                        }
                        
                        // Get direct branch phone
                        if ($record->phone) {
                            $contactMethods[] = "<div class='flex items-center gap-1'>
                                <svg class='w-4 h-4 text-green-500' fill='currentColor' viewBox='0 0 20 20'>
                                    <path d='M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z'></path>
                                </svg>
                                <span class='text-green-600'>Phone</span>
                            </div>";
                        }
                        
                        if (empty($contactMethods)) {
                            return '<span class="text-gray-400">No contact information available</span>';
                        }
                        
                        return implode('<div class="mt-1"></div>', $contactMethods);
                    }),

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
                    TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Hold' => 'warning',
                        default => 'gray',
                    }),

            ])
            ->filters([
                SelectFilter::make('service_type')
                    ->label('Service Type')->searchable()
                    ->options(function () {
                        return ServiceType::pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('services', function (Builder $query) use ($data) {
                                $query->where('service_type_id', $data['value']);
                            });
                        }
                        return $query;
                    }),

                    SelectFilter::make('provider_country')
                    ->label('Provider Country')
                    ->searchable()
                    ->options(function () {
                        return Country::whereHas('providers.branches')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('provider', function (Builder $query) use ($data) {
                                $query->where('country_id', $data['value']);
                            });
                        }
                        return $query;
                    }),
                    
                    SelectFilter::make('city')
                     ->label('Branch Cities')
                     ->searchable()
                     ->multiple()
                     ->options(function () {
                         return City::all()
                             ->pluck('name', 'id');
                     })
                     ->query(function (Builder $query, array $data): Builder {
                         if (!empty($data['values'])) {
                             return $query->whereHas('cities', function (Builder $query) use ($data) {
                                 $query->whereIn('cities.id', $data['values']);
                             });
                         }
                         return $query;
                     }),
                    SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                    ]),
                Filter::make('has_email')
                    ->label('Has Email Contact')
                    ->toggle()
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['isActive']) {
                            return $query->where(function (Builder $query) {
                                $query->whereNotNull('email')
                                    ->orWhereHas('operationContact', function (Builder $query) {
                                        $query->whereNotNull('email');
                                    })
                                    ->orWhereHas('gopContact', function (Builder $query) {
                                        $query->whereNotNull('email');
                                    })
                                    ->orWhereHas('financialContact', function (Builder $query) {
                                        $query->whereNotNull('email');
                                    });
                            });
                        }
                        return $query;
                    }),

                Filter::make('has_phone')
                    ->label('Has Phone Contact')
                    ->toggle()
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['isActive']) {
                            return $query->where(function (Builder $query) {
                                $query->whereNotNull('phone')
                                    ->orWhereHas('operationContact', function (Builder $query) {
                                        $query->whereNotNull('phone_number');
                                    })
                                    ->orWhereHas('gopContact', function (Builder $query) {
                                        $query->whereNotNull('phone_number');
                                    })
                                    ->orWhereHas('financialContact', function (Builder $query) {
                                        $query->whereNotNull('phone_number');
                                    });
                            });
                        }
                        return $query;
                    }),
                    
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('showPhone')
                    ->label('Show Phone')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->visible(fn (ProviderBranch $record): bool => !empty($record->phone))
                    ->action(fn (ProviderBranch $record) => $this->showPhoneNotification($record->id))
                    ->tooltip('Click to show phone number in notification'),
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

        $emailData = $this->data['customEmails'] ?? [];
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

        $emailData = $this->data['customEmails'] ?? [];
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

    protected function applyFileBasedFilters(): void
    {
        if (!$this->selectedFile) {
            return;
        }

        $filters = [];

        // Apply city filter if file has a city
        if ($this->selectedFile->city_id) {
            $filters['city'] = [
                'values' => [$this->selectedFile->city_id]
            ];
        }

        // Apply service type filter if file has a service type
        if ($this->selectedFile->service_type_id) {
            $filters['service_type'] = [
                'value' => $this->selectedFile->service_type_id
            ];
        }

        // Apply the filters to the table
        $this->tableFilters = array_merge($this->tableFilters ?? [], $filters);
    }

    protected function clearFileBasedFilters(): void
    {
        if (isset($this->tableFilters)) {
            // Remove the file-based filters
            unset($this->tableFilters['city']);
            unset($this->tableFilters['service_type']);
        }
    }

    public function showPhoneNotification($branchId): void
    {
        try {
            $branch = ProviderBranch::find($branchId);
            
            if (!$branch) {
                Notification::make()
                    ->title('Error')
                    ->body('Branch not found.')
                    ->danger()
                    ->send();
                return;
            }

            // Get the direct branch phone number
            $phoneNumber = $branch->phone;
            
            if ($phoneNumber) {
                Notification::make()
                    ->title("📞 {$branch->branch_name}'s Phone")
                    ->body("Phone: {$phoneNumber}")
                    ->success()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('No Phone Number Available')
                    ->body("{$branch->branch_name} does not have a direct phone number.")
                    ->warning()
                    ->persistent()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Failed to retrieve phone number: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }


}