<?php

namespace App\Filament\Pages;

use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\City;
use App\Services\GoogleDistanceService;
use App\Mail\NotifyBranchMailable;
use App\Models\Appointment;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table as FilamentTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FileRequestAppointment extends Page implements HasTable
{
    use InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $title = 'Request Appointment';
    protected static ?string $slug = 'file-request-appointment/{record}';
    protected static ?string $navigationGroup = 'Files';
    protected static ?int $navigationSort = 10;

    public File $file;
    public $record;

    public function mount($record): void
    {
        $this->record = $record;
        $this->file = File::with(['patient', 'city', 'country', 'serviceType'])->findOrFail($record);
    }



    public function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                CheckboxColumn::make('selected')
                    ->label('Select'),
                
                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->action(
                        Action::make('view_branch')
                            ->label('View Branch')
                            ->url(fn (ProviderBranch $record) => route('filament.admin.resources.provider-branches.edit', $record))
                            ->openUrlInNewTab()
                    ),
                
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '1', '2' => 'success',
                        '3', '4' => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('services')
                    ->label('Services')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        return $record->branchServices()
                            ->where('service_type_id', $this->file->service_type_id)
                            ->where('is_active', true)
                            ->get()
                            ->pluck('serviceType.name')
                            ->implode(', ');
                    })
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('cost')
                    ->label('Cost')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        $branchService = $record->branchServices()
                            ->where('service_type_id', $this->file->service_type_id)
                            ->where('is_active', true)
                            ->first();
                        
                        if (!$branchService) {
                            return 'N/A';
                        }
                        
                        return number_format($branchService->day_cost, 2) . ' €';
                    })
                    ->sortable(),
                
                TextColumn::make('distance')
                    ->label('Distance')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        return $this->getDistanceToBranch($record);
                    })
                    ->sortable(),
                
                TextColumn::make('contact_info')
                    ->label('Contact Info')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        $hasEmail = $record->getPrimaryEmailAttribute();
                        $hasPhone = $record->getPrimaryPhoneAttribute();
                        
                        if ($hasEmail && $hasPhone) {
                            return 'Email, Phone';
                        } elseif ($hasEmail) {
                            return 'Email';
                        } elseif ($hasPhone) {
                            return 'Phone';
                        }
                        
                        return 'None';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Email, Phone' => 'success',
                        'Email' => 'info',
                        'Phone' => 'warning',
                        default => 'gray',
                    })
                    ->action(
                        Action::make('show_phone')
                            ->label('Show Phone')
                            ->visible(fn (ProviderBranch $record) => $record->getPrimaryPhoneAttribute())
                            ->action(function (ProviderBranch $record) {
                                $phone = $record->getPrimaryPhoneAttribute();
                                Notification::make()
                                    ->title("Branch {$record->branch_name} phone number")
                                    ->body($phone)
                                    ->persistent()
                                    ->success()
                                    ->send();
                            })
                    ),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Hold',
                    ]),
            ])
            ->filters([
                SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->options(ServiceType::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('branchServices', function (Builder $query) use ($data) {
                                $query->where('service_type_id', $data['value'])
                                    ->where('is_active', 1);
                            });
                        }
                        return $query;
                    }),
                
                SelectFilter::make('country')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('provider', function (Builder $query) use ($data) {
                                $query->where('country_id', $data['value']);
                            });
                        }
                        return $query;
                    }),
                
                SelectFilter::make('city')
                    ->label('City')
                    ->options(function () {
                        $countryId = request()->get('tableFilters.country.value');
                        if ($countryId) {
                            return City::where('country_id', $countryId)->pluck('name', 'id');
                        }
                        return City::pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->where('city_id', $data['value']);
                        }
                        return $query;
                    }),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                    ]),
                
                Filter::make('has_email')
                    ->label('Has Email')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email')
                        ->orWhereHas('operationContact', fn ($q) => $q->whereNotNull('email'))
                        ->orWhereHas('gopContact', fn ($q) => $q->whereNotNull('email'))
                        ->orWhereHas('financialContact', fn ($q) => $q->whereNotNull('email'))
                    ),
                
                Filter::make('has_phone')
                    ->label('Has Phone')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('phone')
                        ->orWhereHas('operationContact', fn ($q) => $q->whereNotNull('phone_number'))
                        ->orWhereHas('gopContact', fn ($q) => $q->whereNotNull('phone_number'))
                        ->orWhereHas('financialContact', fn ($q) => $q->whereNotNull('phone_number'))
                    ),
            ])
            ->bulkActions([
                BulkAction::make('sendAppointmentRequests')
                    ->label('Send Appointment Requests')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->action(function (array $records) {
                        $this->sendAppointmentRequests($records);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Send Appointment Requests')
                    ->modalDescription('Are you sure you want to send appointment requests to the selected provider branches?')
                    ->modalSubmitActionLabel('Send Requests')
            ])
            ->defaultSort('priority', 'asc');
    }





    public function getTableQuery(): Builder
    {
        return $this->getProviderBranchesQuery();
    }

    protected function getProviderBranchesQuery(): Builder
    {
        $serviceTypeId = $this->file->service_type_id;
        $countryId = $this->file->country_id;
        $cityId = $this->file->city_id;

        $query = ProviderBranch::query()
            ->with(['city', 'provider', 'branchServices.serviceType', 'operationContact', 'gopContact', 'financialContact'])
            ->where('status', 'Active')
            ->whereHas('branchServices', function ($q) use ($serviceTypeId) {
                $q->where('service_type_id', $serviceTypeId)
                  ->where('is_active', true);
            });

        // If service type is 2 (telemedicine), ignore country/city filters
        if ($serviceTypeId == 2) {
            return $query->orderBy('priority', 'asc');
        }

        // If no country is assigned, show all branches with matching service type
        if (!$countryId) {
            return $query->orderBy('priority', 'asc');
        }

        // Filter branches by country and city
        return $query->whereHas('provider', fn ($q) => $q->where('country_id', $countryId))
            ->where(function ($q) use ($cityId) {
                $q->where('all_country', true)
                  ->orWhere('city_id', $cityId)
                  ->orWhereHas('branchCities', fn ($q) => $q->where('city_id', $cityId));
            })
            ->orderBy('priority', 'asc');
    }

    protected function getDistanceToBranch(ProviderBranch $branch): string
    {
        $distanceService = app(GoogleDistanceService::class);
        $distanceData = $distanceService->calculateFileToBranchDistance($this->file, $branch);
        
        return $distanceService->getFormattedDistance($distanceData);
    }

    protected function sendAppointmentRequests(array $selectedBranches): void
    {
        $successfulBranches = [];
        $failedBranches = [];
        $newAppointments = [];
        $updatedAppointments = [];

        foreach ($selectedBranches as $branchId) {
            $providerBranch = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->find($branchId);
            
            if (!$providerBranch) {
                $failedBranches[] = "Branch ID {$branchId} (Not found)";
                continue;
            }

            try {
                // Create or update appointment
                $appointment = Appointment::updateOrCreate(
                    [
                        'file_id' => $this->file->id,
                        'provider_branch_id' => $branchId,
                    ],
                    [
                        'status' => 'Requested',
                        'requested_at' => now(),
                    ]
                );

                if ($appointment->wasRecentlyCreated) {
                    $newAppointments[] = $providerBranch->branch_name;
                } else {
                    $updatedAppointments[] = $providerBranch->branch_name;
                }

                // Get email addresses
                $emails = collect();
                
                // Add branch's primary email
                if ($providerBranch->getPrimaryEmailAttribute()) {
                    $emails->push($providerBranch->getPrimaryEmailAttribute());
                }
                


                // Send email notification
                if ($emails->isNotEmpty()) {
                    foreach ($emails->unique() as $email) {
                        try {
                            Mail::to($email)->send(new NotifyBranchMailable('appointment_created', $appointment));
                        } catch (\Exception $e) {
                            Log::error('Failed to send appointment request email', [
                                'email' => $email,
                                'branch_id' => $branchId,
                                'file_id' => $this->file->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    $successfulBranches[] = $providerBranch->branch_name;
                } else {
                    $failedBranches[] = $providerBranch->branch_name . ' (No email available)';
                }

            } catch (\Exception $e) {
                Log::error('Failed to process appointment request', [
                    'branch_id' => $branchId,
                    'file_id' => $this->file->id,
                    'error' => $e->getMessage()
                ]);
                $failedBranches[] = $providerBranch->branch_name . ' (Error: ' . $e->getMessage() . ')';
            }
        }

        // Show notification
        $message = "✅ Sent to " . count($successfulBranches) . " providers";
        if (!empty($newAppointments)) {
            $message .= " (Created " . count($newAppointments) . " new appointments)";
        }
        if (!empty($updatedAppointments)) {
            $message .= " (Updated " . count($updatedAppointments) . " existing appointments)";
        }
        if (!empty($failedBranches)) {
            $message .= " ⚠️ Failed to send to " . count($failedBranches) . " providers";
        }

        Notification::make()
            ->title('Appointment Requests Sent')
            ->body($message)
            ->success()
            ->send();

        // Log detailed results
        Log::info('Appointment requests sent', [
            'file_id' => $this->file->id,
            'successful_branches' => $successfulBranches,
            'failed_branches' => $failedBranches,
            'new_appointments' => $newAppointments,
            'updated_appointments' => $updatedAppointments,
        ]);
    }

    public function getTitle(): string
    {
        return "Request Appointment - {$this->file->mga_reference}";
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }





    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        $panel = $panel ?? 'admin';
        $slug = static::$slug;
        
        // Replace {record} placeholder with actual record ID
        if (isset($parameters['record'])) {
            $slug = str_replace('{record}', $parameters['record'], $slug);
        }
        
        $url = "/{$panel}/{$slug}";
        
        return $isAbsolute ? url($url) : $url;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back_to_file')
                ->label('Back to File')
                ->icon('heroicon-o-arrow-left')
                ->url(route('filament.admin.resources.files.view', $this->file))
                ->color('gray'),
        ];
    }


}
