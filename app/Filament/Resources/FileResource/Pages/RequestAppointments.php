<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
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
use App\Mail\AppointmentRequestMail;
use Livewire\Attributes\On;

class RequestAppointments extends ListRecords
{
    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.resources.file-resource.pages.request-appointments';

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
        
        $record = request()->route('record');
        $this->file = File::findOrFail($record);
        
        // Set default filters
        $this->statusFilter = 'Active';
        $this->countryFilter = $this->file->service_type_id === 2 ? '' : $this->file->country_id;
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
        $branches = $this->getBranchesQuery()->get();
        $this->selectedBranches = $branches->pluck('id')->toArray();
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
        $query = \App\Models\ProviderBranch::with([
            'provider.country',
            'operationContact',
            'gopContact', 
            'financialContact',
            'cities',
            'branchServices.serviceType'
        ])
        ->whereHas('branchServices', function ($q) {
            $q->where('service_type_id', $this->file->service_type_id)
              ->where('is_active', 1);
        });

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
        return $service->calculateFileToBranchDistance($this->file, $branch);
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

    public function hasPhone($branch)
    {
        return $branch->phone || 
               $branch->operationContact?->phone_number || 
               $branch->gopContact?->phone_number || 
               $branch->financialContact?->phone_number;
    }

    public function getPhoneInfo($branchId)
    {
        $branch = \App\Models\ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->find($branchId);
        
        if (!$branch) {
            return null;
        }

        return [
            'branch_name' => $branch->branch_name,
            'direct_phone' => $branch->phone,
            'operation_contact' => [
                'name' => $branch->operationContact?->name,
                'phone' => $branch->operationContact?->phone_number,
                'email' => $branch->operationContact?->email,
            ],
            'gop_contact' => [
                'name' => $branch->gopContact?->name,
                'phone' => $branch->gopContact?->phone_number,
                'email' => $branch->gopContact?->email,
            ],
            'financial_contact' => [
                'name' => $branch->financialContact?->name,
                'phone' => $branch->financialContact?->phone_number,
                'email' => $branch->financialContact?->email,
            ],
        ];
    }

    public function formatPhoneInfo($phoneInfo)
    {
        $output = "**{$phoneInfo['branch_name']}**\n\n";
        
        if ($phoneInfo['direct_phone']) {
            $output .= "**Direct Phone:** {$phoneInfo['direct_phone']}\n";
        }
        
        if ($phoneInfo['operation_contact']['name']) {
            $output .= "\n**Operation Contact:** {$phoneInfo['operation_contact']['name']}\n";
            if ($phoneInfo['operation_contact']['phone']) {
                $output .= "Phone: {$phoneInfo['operation_contact']['phone']}\n";
            }
            if ($phoneInfo['operation_contact']['email']) {
                $output .= "Email: {$phoneInfo['operation_contact']['email']}\n";
            }
        }
        
        if ($phoneInfo['gop_contact']['name']) {
            $output .= "\n**GOP Contact:** {$phoneInfo['gop_contact']['name']}\n";
            if ($phoneInfo['gop_contact']['phone']) {
                $output .= "Phone: {$phoneInfo['gop_contact']['phone']}\n";
            }
            if ($phoneInfo['gop_contact']['email']) {
                $output .= "Email: {$phoneInfo['gop_contact']['email']}\n";
            }
        }
        
        if ($phoneInfo['financial_contact']['name']) {
            $output .= "\n**Financial Contact:** {$phoneInfo['financial_contact']['name']}\n";
            if ($phoneInfo['financial_contact']['phone']) {
                $output .= "Phone: {$phoneInfo['financial_contact']['phone']}\n";
            }
            if ($phoneInfo['financial_contact']['email']) {
                $output .= "Email: {$phoneInfo['financial_contact']['email']}\n";
            }
        }
        
        return $output;
    }

    public function sendRequests()
    {
        $customEmails = collect($this->customEmails)
            ->pluck('email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->toArray();

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
                        \Mail::to($email)->send(new \App\Mail\AppointmentRequestMail($this->file, $providerBranch));
                        $successfulBranches[] = $providerBranch->branch_name;
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
                    \Mail::to($email)->send(new \App\Mail\AppointmentRequestMail($this->file, null));
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


                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => route('filament.admin.resources.provider-branches.overview', $record))
                    ->openUrlInNewTab()
                    ->color('primary'),

                TextColumn::make('provider.name')
                    ->label('Provider')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('priority')
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
                    ->formatStateUsing(function ($state, $record) {
                        $serviceTypeId = $this->file->service_type_id;
                        $cities = $record->cities()
                            ->whereHas('branchServices', function ($q) use ($serviceTypeId) {
                                $q->where('service_type_id', $serviceTypeId);
                            })
                            ->pluck('name')
                            ->unique()
                            ->filter()
                            ->implode(', ');
                        return $cities ?: 'N/A';
                    }),

                TextColumn::make('cost')
                    ->label('Cost')
                    ->formatStateUsing(function ($state, $record) {
                        $serviceTypeId = $this->file->service_type_id;
                        $branchService = $record->branchServices()
                            ->where('service_type_id', $serviceTypeId)
                            ->where('is_active', true)
                            ->first();
                        
                        if ($branchService && $branchService->day_cost) {
                            return '€' . number_format($branchService->day_cost, 2);
                        }
                        return 'N/A';
                    }),

                TextColumn::make('distance')
                    ->label('Distance')
                    ->formatStateUsing(function ($state, $record) {
                        // Get file address
                        $fileAddress = $this->file->patient->address ?? '';
                        
                        // Get branch address
                        $branchAddress = $record->address ?? '';
                        
                        if ($fileAddress && $branchAddress) {
                            try {
                                $distanceService = app(\App\Services\DistanceCalculationService::class);
                                $distance = $distanceService->calculateDistance($fileAddress, $branchAddress);
                                return number_format($distance, 1) . ' km';
                            } catch (\Exception $e) {
                                return 'N/A';
                            }
                        }
                        return 'N/A';
                    }),

                TextColumn::make('contact_info')
                    ->label('Contact Info')
                    ->formatStateUsing(function ($state, $record) {
                        $badges = [];
                        
                        // Check for email
                        if ($record->email || 
                            ($record->operationContact && $record->operationContact->email) ||
                            ($record->gopContact && $record->gopContact->email) ||
                            ($record->financialContact && $record->financialContact->email)) {
                            $badges[] = 'Email';
                        }
                        
                        // Check for phone
                        if ($record->phone || 
                            ($record->operationContact && $record->operationContact->phone_number) ||
                            ($record->gopContact && $record->gopContact->phone_number) ||
                            ($record->financialContact && $record->financialContact->phone_number)) {
                            $badges[] = 'Phone';
                        }
                        
                        return implode(', ', $badges);
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Email' => 'success',
                        'Phone' => 'info',
                        'Email, Phone' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status')
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
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->whereHas('branchServices', fn ($q) => $q->where('service_type_id', $value)))),

                SelectFilter::make('countryFilter')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->searchable()
                    ->default($this->file->country_id)
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->whereHas('provider', fn ($q) => $q->where('country_id', $value)))),

                SelectFilter::make('cityFilter')
                    ->label('City')
                    ->options(function() {
                        // Get all cities from countries that have providers with branches
                        $cities = \App\Models\City::whereHas('country', function($q) {
                            $q->whereIn('id', \App\Models\Provider::whereHas('branches')->pluck('country_id'));
                        })->pluck('name', 'id');
                        return $cities->toArray();
                    })
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->whereHas('cities', fn ($q) => $q->where('cities.id', $value)))),

                SelectFilter::make('statusFilter')
                    ->label('Provider Status')
                    ->options([
                        'Active' => 'Active',
                        'Potential' => 'Potential',
                        'Hold' => 'Hold',
                    ])
                    ->default('Active')
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->whereHas('provider', fn ($q) => $q->where('status', $value)))),

                Filter::make('showOnlyWithEmail')
                    ->label('Show Only Branches with Email')
                    ->default(true)
                    ->query(fn (Builder $query, array $data) => isset($data['value']) && $data['value'] ? $query->where(function($q) {
                        $q->whereNotNull('email')->where('email', '!=', '')
                          ->orWhereHas('operationContact', fn($oc) => $oc->whereNotNull('email')->where('email', '!=', ''))
                          ->orWhereHas('gopContact', fn($gc) => $gc->whereNotNull('email')->where('email', '!=', ''))
                          ->orWhereHas('financialContact', fn($fc) => $fc->whereNotNull('email')->where('email', '!=', ''));
                    }) : $query),

                Filter::make('showOnlyWithPhone')
                    ->label('Show Only Branches with Phone')
                    ->default(true)
                    ->query(fn (Builder $query, array $data) => isset($data['value']) && $data['value'] ? $query->where(function($q) {
                        $q->whereNotNull('phone')->where('phone', '!=', '')
                          ->orWhereHas('operationContact', fn($oc) => $oc->whereNotNull('phone_number')->where('phone_number', '!=', ''))
                          ->orWhereHas('gopContact', fn($gc) => $gc->whereNotNull('phone_number')->where('phone_number', '!=', ''))
                          ->orWhereHas('financialContact', fn($fc) => $fc->whereNotNull('phone_number')->where('phone_number', '!=', ''));
                    }) : $query),
            ])
            ->bulkActions([
                BulkAction::make('selectAll')
                    ->label('Select All')
                    ->action(fn () => $this->selectAll()),

                BulkAction::make('clearSelection')
                    ->label('Clear Selection')
                    ->action(fn () => $this->clearSelection()),
            ])
            ->defaultSort('priority', 'asc')
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
            
            Action::make('sendRequests')
                ->label('Send Appointment Requests')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->action('sendRequests')
                ->requiresConfirmation()
                ->modalHeading('Send Appointment Requests')
                ->modalDescription('Are you sure you want to send appointment requests to the selected providers?')
                ->modalSubmitActionLabel('Send Requests'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
