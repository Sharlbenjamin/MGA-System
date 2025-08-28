<?php

namespace App\Filament\Resources\ProviderBranchResource\Pages;

use App\Filament\Resources\FileResource;
use App\Filament\Resources\ProviderBranchResource;
use App\Models\File;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\ProviderBranch;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentNotificationMail;
use Livewire\Attributes\On;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfolistSection;

class RequestAppointments extends ListRecords
{
    protected static string $resource = ProviderBranchResource::class;

    protected static string $view = 'filament.resources.provider-branch-resource.pages.request-appointments';

    public File $file;
    public $search = '';
    public $serviceTypeFilter = '';
    public $countryFilter = '';
    public $statusFilter = '';
    public $showProvinceBranches = false;
    public $showOnlyWithEmail = false;
    public $showOnlyWithPhone = false;
    public $sortField = 'priority';
    public $sortDirection = 'asc';
    public $selectedBranches = [];
    public $customEmails = [];
    public $selectedBranchForPhone = null;

    public function mount(): void
    {
        parent::mount();
        
        // Get the record parameter from the route
        $recordId = request()->route('record');
        
        // Ensure we have a valid record ID
        if (!$recordId) {
            abort(404, 'File not found');
        }
        
        // Load the file with all necessary relationships
        $this->file = File::with([
            'patient',
            'serviceType',
            'city',
            'country'
        ])->findOrFail($recordId);
        
        // Set default filters
        $this->statusFilter = 'Active';
    }

    public function getBranches()
    {
        return $this->getBranchesQuery()->paginate(20);
    }

    public function updatedSearch()
    {
        $this->selectedBranches = [];
    }

    public function updatedServiceTypeFilter()
    {
        $this->selectedBranches = [];
    }

    public function updatedCountryFilter()
    {
        $this->selectedBranches = [];
    }

    public function updatedStatusFilter()
    {
        $this->selectedBranches = [];
    }

    public function updatedShowOnlyWithEmail()
    {
        $this->selectedBranches = [];
    }

    public function updatedShowOnlyWithPhone()
    {
        $this->selectedBranches = [];
    }

    public function selectAll()
    {
        $branchServices = $this->getBranchesQuery()->get();
        $this->selectedBranches = $branchServices->pluck('providerBranch.id')->unique()->toArray();
    }

    public function clearSelection()
    {
        $this->selectedBranches = [];
    }

    public function addCustomEmail()
    {
        $this->customEmails[] = ['email' => ''];
    }

    public function removeCustomEmail($index)
    {
        unset($this->customEmails[$index]);
        $this->customEmails = array_values($this->customEmails);
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    protected function getBranchesQuery(): Builder
    {
        $query = \App\Models\BranchService::with([
            'providerBranch.provider.country',
            'providerBranch.operationContact',
            'providerBranch.gopContact', 
            'providerBranch.financialContact',
            'providerBranch.cities',
            'providerBranch.branchCities',
            'serviceType'
        ])
        ->join('provider_branches', 'branch_services.provider_branch_id', '=', 'provider_branches.id')
        ->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
        ->where('branch_services.is_active', 1);

        return $query;
    }

    public function toggleBranch($branchId)
    {
        if (in_array($branchId, $this->selectedBranches)) {
            $this->selectedBranches = array_diff($this->selectedBranches, [$branchId]);
        } else {
            $this->selectedBranches[] = $branchId;
        }
    }

    public function getDistanceToBranch($branch)
    {
        $service = app(\App\Services\DistanceCalculationService::class);
        
        // Get file address
        $fileAddress = $this->file->address;
        if (!$fileAddress) {
            return null;
        }
        
        // Get branch address - prioritize the new direct address column
        $branchAddress = $branch->address;
        
        // If no direct address, fallback to operation contact address
        if (!$branchAddress && $branch->operationContact) {
            $branchAddress = $branch->operationContact->address;
        }
        
        // If still no address, try GOP contact
        if (!$branchAddress && $branch->gopContact) {
            $branchAddress = $branch->gopContact->address;
        }
        
        // If still no address, try financial contact
        if (!$branchAddress && $branch->financialContact) {
            $branchAddress = $branch->financialContact->address;
        }
        
        if (!$branchAddress) {
            return null;
        }
        
        // Calculate distance between file address and branch address
        return $service->calculateDistance($fileAddress, $branchAddress);
    }

    public function getCostForService($branch, $serviceTypeId)
    {
        return $branch->getCostForService($serviceTypeId);
    }

    public function hasEmail($branch)
    {
        return $branch->email || 
               $branch->operationContact?->email || 
               $branch->gopContact?->email || 
               $branch->financialContact?->email;
    }



    public function sendRequests()
    {
        $customEmails = collect($this->customEmails)
            ->pluck('email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->toArray();

        // Debug: Log the selected branches and custom emails
        \Log::info('Selected branches: ' . json_encode($this->selectedBranches));
        \Log::info('Custom emails: ' . json_encode($customEmails));

        // If no branches or custom emails selected, show warning
        if (empty($this->selectedBranches) && empty($customEmails)) {
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
        DB::transaction(function () use (&$successfulBranches, &$skippedBranches, &$updatedAppointments, &$newAppointments, $customEmails) {
            foreach ($this->selectedBranches as $branchId) {
                $providerBranch = \App\Models\ProviderBranch::find($branchId);
                
                if (!$providerBranch) {
                    continue;
                }

                // Check if an appointment already exists
                $existingAppointment = $this->file->appointments()
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
                } else {
                    // Create new appointment
                    $appointment = $this->file->appointments()->create([
                        'provider_branch_id' => $branchId,
                        'service_date' => now()->toDateString(),
                        'status' => 'Requested',
                        'created_by' => auth()->id(),
                    ]);
                    $newAppointments[] = $providerBranch->branch_name;
                }

                // Send email notification
                try {
                    $email = $providerBranch->email ?: 
                             $providerBranch->operationContact?->email ?: 
                             $providerBranch->gopContact?->email ?: 
                             $providerBranch->financialContact?->email;

                    if ($email) {
                        // Get the appointment that was just created or updated
                        $appointment = $this->file->appointments()
                            ->where('provider_branch_id', $branchId)
                            ->first();
                        
                        if ($appointment) {
                            \Mail::to($email)->send(new \App\Mail\NotifyBranchMailable('appointment_created', $appointment));
                            $successfulBranches[] = $providerBranch->branch_name;
                        } else {
                            $skippedBranches[] = $providerBranch->branch_name . ' (No appointment created)';
                        }
                    } else {
                        $skippedBranches[] = $providerBranch->branch_name . ' (No email)';
                    }
                } catch (\Exception $e) {
                    $skippedBranches[] = $providerBranch->branch_name . ' (Email failed)';
                }
            }

            // Handle custom emails
            foreach ($customEmails as $email) {
                try {
                    // For custom emails, we don't have an appointment, so we'll use a different approach
                    // Create a temporary appointment object for the email
                    $tempAppointment = new \App\Models\Appointment([
                        'file_id' => $this->file->id,
                        'provider_branch_id' => null,
                        'service_date' => now()->toDateString(),
                        'status' => 'Requested',
                    ]);
                    
                    // Set the file relationship
                    $tempAppointment->setRelation('file', $this->file);
                    
                    \Mail::to($email)->send(new \App\Mail\NotifyBranchMailable('appointment_created', $tempAppointment));
                    $successfulBranches[] = 'Custom: ' . $email;
                } catch (\Exception $e) {
                    $skippedBranches[] = 'Custom: ' . $email . ' (Email failed)';
                }
            }
        });

        // Show success notification
        $message = "Successfully sent " . count($successfulBranches) . " appointment requests.";
        if (!empty($updatedAppointments)) {
            $message .= " Updated " . count($updatedAppointments) . " existing appointments.";
        }
        if (!empty($newAppointments)) {
            $message .= " Created " . count($newAppointments) . " new appointments.";
        }
        if (!empty($skippedBranches)) {
            $message .= " Skipped " . count($skippedBranches) . " branches.";
        }

        Notification::make()
            ->title('Appointment Requests Sent')
            ->body($message)
            ->success()
            ->send();

        // Redirect back to the file view
        return redirect()->route('filament.admin.resources.files.view', $this->file);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getBranchesQuery())
            ->columns([
                TextColumn::make('selected')
                    ->label('Select')
                    ->getStateUsing(fn ($record): string => in_array($record->providerBranch->id, $this->selectedBranches) ? '✓' : '')
                    ->action(function ($record) {
                        if (in_array($record->providerBranch->id, $this->selectedBranches)) {
                            $this->selectedBranches = array_diff($this->selectedBranches, [$record->providerBranch->id]);
                        } else {
                            $this->selectedBranches[] = $record->providerBranch->id;
                        }
                    })
                    ->alignCenter()
                    ->color('success'),


                TextColumn::make('providerBranch.branch_name')
                    ->label('Branch Name')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => \App\Filament\Resources\ProviderBranchResource::getUrl('overview', ['record' => $record->providerBranch]))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('providerBranch.provider.name')
                    ->label('Provider')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('providerBranch.priority')
                    ->label('Priority')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'High' => 'danger',
                        'Medium' => 'warning',
                        'Low' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('cities')
                    ->label('City')
                    ->getStateUsing(function ($record) {
                        // Get cities from the branch's cities relationship
                        $cities = $record->providerBranch->cities()->pluck('name')->unique()->filter()->implode(', ');
                        return $cities ?: 'N/A';
                    }),

                TextColumn::make('cost')
                    ->label('Cost')
                    ->getStateUsing(function ($record) {
                        // Get cost directly from the branch service record
                        $cost = $record->day_cost;
                        return $cost ? '€' . number_format($cost, 2) : 'N/A';
                    }),

                TextColumn::make('distance')
                    ->label('Distance')
                    ->getStateUsing(function ($record) {
                        // Use the existing helper method that was working
                        $distanceData = $this->getDistanceToBranch($record->providerBranch);
                        if ($distanceData && isset($distanceData['duration_minutes'])) {
                            return number_format($distanceData['duration_minutes'], 1) . ' min';
                        }
                        return 'N/A';
                    }),

                TextColumn::make('contact_info')
                    ->label('Contact Info')
                    ->getStateUsing(function ($record) {
                        // Only show email badge, remove phone functionality
                        if ($this->hasEmail($record->providerBranch)) {
                            return 'Email';
                        }
                        return 'No Email';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Email' => 'success',
                        'No Email' => 'gray',
                        default => 'gray',
                    })
                    ->tooltip('Email availability'),

                TextColumn::make('providerBranch.provider.status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Potential' => 'warning',
                        'Hold' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('serviceTypeFilter')
                    ->label('Service Type')
                    ->options(ServiceType::pluck('name', 'id'))
                    ->default($this->file->service_type_id)
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->where('branch_services.service_type_id', $value))),

                SelectFilter::make('countryFilter')
                    ->label('Country')
                    ->options(function() {
                        // Only show the file's country and countries that have providers with branches
                        $countries = \App\Models\Country::whereHas('providers', function($q) {
                            $q->whereHas('branches');
                        });
                        
                        // If file has a country, prioritize it
                        if ($this->file->country_id) {
                            $countries = $countries->orWhere('id', $this->file->country_id);
                        }
                        
                        return $countries->pluck('name', 'id')->toArray();
                    })
                    ->default($this->file->service_type_id === 2 ? null : $this->file->country_id)
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->where('providers.country_id', $value))),

                SelectFilter::make('cityFilter')
                    ->label('City')
                    ->options(function() {
                        // Only show cities from the file's country that have branches
                        if ($this->file->country_id) {
                            $cities = \App\Models\City::where('country_id', $this->file->country_id)
                                ->whereHas('branchCities.providerBranch.provider', function($q) {
                                    $q->whereHas('branches');
                                })
                                ->pluck('name', 'id');
                            return $cities->toArray();
                        }
                        return [];
                    })
                    ->default($this->file->city_id)
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->whereExists(function($subQuery) use ($value) {
                        $subQuery->select(\DB::raw(1))
                            ->from('branch_cities')
                            ->whereColumn('branch_cities.provider_branch_id', 'provider_branches.id')
                            ->where('branch_cities.city_id', $value);
                    }))),

                SelectFilter::make('statusFilter')
                    ->label('Provider Status')
                    ->options([
                        'Active' => 'Active',
                        'Potential' => 'Potential',
                        'Hold' => 'Hold',
                    ])
                    ->default('Active')
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->where('providers.status', $value))),

                Filter::make('showOnlyWithEmail')
                    ->label('Show Only Branches with Email')
                    ->query(fn (Builder $query, array $data) => isset($data['value']) && $data['value'] ? $query->where(function($q) {
                        $q->whereNotNull('provider_branches.email')->where('provider_branches.email', '!=', '')
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(\DB::raw(1))
                                  ->from('contacts')
                                  ->whereColumn('contacts.id', 'provider_branches.operation_contact_id')
                                  ->whereNotNull('contacts.email')->where('contacts.email', '!=', '');
                          })
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(\DB::raw(1))
                                  ->from('contacts')
                                  ->whereColumn('contacts.id', 'provider_branches.gop_contact_id')
                                  ->whereNotNull('contacts.email')->where('contacts.email', '!=', '');
                          })
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(\DB::raw(1))
                                  ->from('contacts')
                                  ->whereColumn('contacts.id', 'provider_branches.financial_contact_id')
                                  ->whereNotNull('contacts.email')->where('contacts.email', '!=', '');
                          });
                    }) : $query),


            ])
            ->bulkActions([
                BulkAction::make('sendRequests')
                    ->label('Send Appointment Requests')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->action(function ($records) {
                        // Get the branch IDs from the selected branch services
                        $selectedBranchIds = $records->pluck('providerBranch.id')->unique()->toArray();
                        $this->selectedBranches = $selectedBranchIds;
                        $this->sendRequests();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Send Appointment Requests')
                    ->modalDescription('Are you sure you want to send appointment requests to the selected providers?')
                    ->modalSubmitActionLabel('Send Requests'),

                BulkAction::make('selectAll')
                    ->label('Select All')
                    ->action(fn () => $this->selectAll()),

                BulkAction::make('clearSelection')
                    ->label('Clear Selection')
                    ->action(fn () => $this->clearSelection()),
            ])
            ->defaultSort('provider_branches.priority', 'asc')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToFile')
                ->label('Back to File')
                ->icon('heroicon-o-arrow-left')
                ->url(route('filament.admin.resources.files.view', $this->file))
                ->color('gray'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make()
                    ->schema([
                        TextEntry::make('patient_name')
                            ->label('Patient Name')
                            ->getStateUsing(fn () => $this->file->patient->name)
                            ->color('danger')
                            ->weight('bold'),
                        
                        TextEntry::make('mga_reference')
                            ->label('MGA Reference')
                            ->getStateUsing(fn () => $this->file->mga_reference)
                            ->color('warning')
                            ->weight('bold'),
                        
                        TextEntry::make('client_reference')
                            ->label('Client Reference')
                            ->getStateUsing(fn () => $this->file->client_reference ?? 'N/A')
                            ->color('info'),
                        
                        TextEntry::make('service_type')
                            ->label('Service Type')
                            ->getStateUsing(fn () => $this->file->serviceType->name)
                            ->color('success')
                            ->weight('bold'),
                        
                        TextEntry::make('city')
                            ->label('City')
                            ->getStateUsing(fn () => $this->file->city?->name ?? 'N/A')
                            ->color('primary'),
                        
                        TextEntry::make('country')
                            ->label('Country')
                            ->getStateUsing(fn () => $this->file->country?->name ?? 'N/A')
                            ->color('primary'),
                        
                        TextEntry::make('address')
                            ->label('Address')
                            ->getStateUsing(fn () => $this->file->address ?? 'N/A')
                            ->color('gray'),
                        
                        TextEntry::make('symptoms')
                            ->label('Symptoms')
                            ->getStateUsing(fn () => $this->file->symptoms ?? 'N/A')
                            ->color('gray'),
                    ])
                    ->columns(4),
            ]);
    }
}
