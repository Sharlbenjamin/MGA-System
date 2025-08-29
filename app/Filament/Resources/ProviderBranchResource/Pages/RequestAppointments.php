<?php

namespace App\Filament\Resources\ProviderBranchResource\Pages;

use App\Filament\Resources\ProviderBranchResource;
use App\Models\File;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\ProviderBranch;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotifyBranchMailable;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfolistSection;

class RequestAppointments extends ListRecords
{
    protected static string $resource = ProviderBranchResource::class;

    public function getFile(): File
    {
        $fileId = request()->route('record');
        return File::with(['patient', 'serviceType', 'city', 'country'])->findOrFail($fileId);
    }

    public function getDistanceToBranch($branch)
    {
        $service = app(\App\Services\DistanceCalculationService::class);
        
        // Get file address
        $fileAddress = $this->getFile()->address;
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

    public function hasEmail($branch)
    {
        return $branch->email || 
               $branch->operationContact?->email || 
               $branch->gopContact?->email || 
               $branch->financialContact?->email;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\BranchService::with([
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
            ->where('branch_services.is_active', 1))
            ->columns([
                CheckboxColumn::make('selected'),

                TextColumn::make('providerBranch.branch_name')
                    ->label('Branch Name')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => \App\Filament\Resources\ProviderBranchResource::getUrl('overview', ['record' => $record->provider_branch_id]))
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
                        $cities = DB::table('branch_cities')
                            ->join('cities', 'branch_cities.city_id', '=', 'cities.id')
                            ->where('branch_cities.provider_branch_id', $record->provider_branch_id)
                            ->pluck('cities.name')
                            ->unique()
                            ->filter()
                            ->implode(', ');
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
                        // Get the provider branch and calculate distance
                        $providerBranch = ProviderBranch::find($record->provider_branch_id);
                        if ($providerBranch) {
                            $distanceData = $this->getDistanceToBranch($providerBranch);
                            if ($distanceData && isset($distanceData['duration_minutes'])) {
                                return number_format($distanceData['duration_minutes'], 1) . ' min';
                            }
                        }
                        return 'N/A';
                    }),

                TextColumn::make('contact_info')
                    ->label('Contact Info')
                    ->getStateUsing(function ($record) {
                        // Get the provider branch and check for email
                        $providerBranch = ProviderBranch::find($record->provider_branch_id);
                        if ($providerBranch && $this->hasEmail($providerBranch)) {
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
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->where('branch_services.service_type_id', $value))),

                SelectFilter::make('countryFilter')
                    ->label('Country')
                    ->options(function() {
                        $file = $this->getFile();
                        // Only show countries that have providers with branches
                        $countries = Country::whereHas('providers', function($q) {
                            $q->whereHas('branches');
                        });
                        
                        // If file has a country, prioritize it
                        if ($file->country_id) {
                            $countries = $countries->orWhere('id', $file->country_id);
                        }
                        
                        return $countries->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->where('providers.country_id', $value))),

                SelectFilter::make('cityFilter')
                    ->label('City')
                    ->options(function() {
                        $file = $this->getFile();
                        // Only show cities from the file's country that have branches
                        if ($file->country_id) {
                            $cities = \App\Models\City::where('country_id', $file->country_id)
                                ->whereHas('branchCities.branch.provider', function($q) {
                                    $q->whereHas('branches');
                                })
                                ->pluck('name', 'id');
                            return $cities->toArray();
                        }
                        return [];
                    })
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->whereExists(function($subQuery) use ($value) {
                        $subQuery->select(DB::raw(1))
                            ->from('branch_cities')
                            ->whereColumn('branch_cities.provider_branch_id', 'branch_services.provider_branch_id')
                            ->where('branch_cities.city_id', $value);
                    }))),

                SelectFilter::make('statusFilter')
                    ->label('Provider Status')
                    ->options([
                        'Active' => 'Active',
                        'Potential' => 'Potential',
                        'Hold' => 'Hold',
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['value']) && $data['value'], fn ($query, $value) => $query->where('providers.status', $value))),

                Filter::make('showOnlyWithEmail')
                    ->label('Show Only Branches with Email')
                    ->query(fn (Builder $query, array $data) => isset($data['value']) && $data['value'] ? $query->where(function($q) {
                        $q->whereNotNull('provider_branches.email')->where('provider_branches.email', '!=', '')
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(DB::raw(1))
                                  ->from('contacts')
                                  ->whereColumn('contacts.id', 'provider_branches.operation_contact_id')
                                  ->whereNotNull('contacts.email')->where('contacts.email', '!=', '');
                          })
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(DB::raw(1))
                                  ->from('contacts')
                                  ->whereColumn('contacts.id', 'provider_branches.gop_contact_id')
                                  ->whereNotNull('contacts.email')->where('contacts.email', '!=', '');
                          })
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(DB::raw(1))
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
                        $this->sendRequests($records);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Send Appointment Requests')
                    ->modalDescription('Are you sure you want to send appointment requests to the selected providers?')
                    ->modalSubmitActionLabel('Send Requests'),
            ])
            ->defaultSort('provider_branches.priority', 'asc')
            ->paginated([10, 25, 50, 100]);
    }

    public function sendRequests($records)
    {
        $selectedBranchIds = $records->pluck('provider_branch_id')->unique()->toArray();
        
        // If no branches selected, show warning
        if (empty($selectedBranchIds)) {
            Notification::make()
                ->title('No Recipients Selected')
                ->body('Please select at least one provider branch.')
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
        DB::transaction(function () use (&$successfulBranches, &$skippedBranches, &$updatedAppointments, &$newAppointments, $selectedBranchIds) {
            foreach ($selectedBranchIds as $branchId) {
                $providerBranch = ProviderBranch::find($branchId);
                
                if (!$providerBranch) {
                    continue;
                }

                // Check if an appointment already exists
                $existingAppointment = $this->getFile()->appointments()
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
                    $appointment = $this->getFile()->appointments()->create([
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
                        $appointment = $this->getFile()->appointments()
                            ->where('provider_branch_id', $branchId)
                            ->first();
                        
                        if ($appointment) {
                            Mail::to($email)->send(new NotifyBranchMailable('appointment_created', $appointment));
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
        return redirect()->route('filament.admin.resources.files.view', $this->getFile());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCustomEmail')
                ->label('Add Custom Email')
                ->icon('heroicon-o-plus')
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data) {
                    // Add custom email to the session or temporary storage
                    session()->push('custom_emails', $data['email']);
                    Notification::make()
                        ->title('Custom email added')
                        ->success()
                        ->send();
                }),
            
            Action::make('backToFile')
                ->label('Back to File')
                ->icon('heroicon-o-arrow-left')
                ->url(route('filament.admin.resources.files.view', $this->getFile()))
                ->color('gray'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\RequestAppointmentsFileInfo::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make()
                    ->schema([
                        TextEntry::make('patient_name')
                            ->label('Patient Name')
                            ->getStateUsing(fn () => $this->getFile()->patient->name)
                            ->color('danger')
                            ->weight('bold'),
                        
                        TextEntry::make('mga_reference')
                            ->label('MGA Reference')
                            ->getStateUsing(fn () => $this->getFile()->mga_reference)
                            ->color('warning')
                            ->weight('bold'),
                        
                        TextEntry::make('client_reference')
                            ->label('Client Reference')
                            ->getStateUsing(fn () => $this->getFile()->client_reference ?? 'N/A')
                            ->color('info'),
                        
                        TextEntry::make('service_type')
                            ->label('Service Type')
                            ->getStateUsing(fn () => $this->getFile()->serviceType->name)
                            ->color('success')
                            ->weight('bold'),
                        
                        TextEntry::make('city')
                            ->label('City')
                            ->getStateUsing(fn () => $this->getFile()->city?->name ?? 'N/A')
                            ->color('primary'),
                        
                        TextEntry::make('country')
                            ->label('Country')
                            ->getStateUsing(fn () => $this->getFile()->country?->name ?? 'N/A')
                            ->color('primary'),
                        
                        TextEntry::make('address')
                            ->label('Address')
                            ->getStateUsing(fn () => $this->getFile()->address ?? 'N/A')
                            ->color('gray'),
                        
                        TextEntry::make('symptoms')
                            ->label('Symptoms')
                            ->getStateUsing(fn () => $this->getFile()->symptoms ?? 'N/A')
                            ->color('gray'),
                    ])
                    ->columns(4),
            ]);
    }
}
