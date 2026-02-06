<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Models\FileFee;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Full-page "Request Appointment" view for a file.
 * Reuses the same form and logic as the previous slide-over modal.
 */
class RequestAppointment extends EditRecord
{
    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.resources.file-resource.pages.request-appointment';

    protected static ?string $title = 'Request Appointment';

    protected static int $requestAppointmentBranchesLimit = 8;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->fillForm();
        $this->previousUrl = url()->previous();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filters')
                    ->description('Filter providers by city (defaults to file\'s city)')
                    ->schema([
                        Select::make('city_filter')
                            ->label('Filter by City')
                            ->options(function () {
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
                                            foreach ($branchIds as $branchId) {
                                                $set("branch_{$branchId}", true);
                                            }
                                        } else {
                                            $set('selected_branches', []);
                                            foreach ($branchIds as $branchId) {
                                                $set("branch_{$branchId}", false);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_branch')->label('Branch Name')->content('')->columnSpan(2),
                                \Filament\Forms\Components\Placeholder::make('header_priority')->label('Priority')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_cost')->label('Cost')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_communication')->label('Contact By')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_contact')->label('Contact')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_phone')->label('Phone')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_address')->label('Address')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_website')->label('Website')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_distance')->label('Distance')->content('')->columnSpan(1),
                                \Filament\Forms\Components\Placeholder::make('header_request')->label('Request')->content('')->columnSpan(1),
                            ])
                            ->extraAttributes(['class' => 'bg-gray-50 border-b-2 border-gray-200 font-semibold text-sm']),
                        ...$this->getBranchRows(),
                        Hidden::make('selected_branches')
                            ->default([])
                            ->rules(['array']),
                    ])
                    ->collapsible(),

                Section::make('Additional Email Recipients')
                    ->description('Add any additional email addresses to receive the appointment request')
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('custom_emails')
                            ->label('Additional Email Recipients')
                            ->schema([
                                TextInput::make('email')->label('Email Address')->email()->required()->placeholder('example@email.com'),
                            ])
                            ->addActionLabel('Add Email')
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['email'] ?? null)
                            ->defaultItems(0),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function fillForm(): void
    {
        $this->form->fill([
            'city_filter' => $this->record->city_id,
            'selected_branches' => [],
            'select_all_branches' => false,
            'custom_emails' => [],
        ]);
    }

    public function sendRequest(): void
    {
        $this->authorizeAccess();
        $data = $this->form->getState();
        $this->sendAppointmentRequestsFromModal($data, $this->getRecord());
        $this->redirect(FileResource::getUrl('view', ['record' => $this->getRecord()]), navigate: true);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Appointment Requests')
                ->submit('sendRequest')
                ->color('primary'),
            Action::make('back')
                ->label('Back to file')
                ->url(FileResource::getUrl('view', ['record' => $this->getRecord()]))
                ->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        return 'Request Appointment – ' . $this->getRecord()->mga_reference;
    }

    public function getBreadcrumb(): string
    {
        return 'Request Appointment';
    }

    protected function getBranchRows(): array
    {
        $cityFilter = $this->data['city_filter'] ?? null;
        $sortedBranches = $this->getDisplayedProviderBranchesForRequest($cityFilter);
        $rows = [];
        foreach ($sortedBranches as $branch) {
            $rows[] = Grid::make(12)
                ->schema([
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
                                $selectedBranches = array_values(array_filter($selectedBranches, fn ($id) => $id != $branch->id));
                            }
                            $set('selected_branches', $selectedBranches);
                            $displayedBranches = $this->getDisplayedProviderBranchesForRequest($get('city_filter'));
                            $totalBranches = $displayedBranches->count();
                            $selectedCount = count($selectedBranches);
                            $set('select_all_branches', $selectedCount === $totalBranches && $totalBranches > 0);
                        })
                        ->columnSpan(1),
                    \Filament\Forms\Components\View::make('branch_name_' . $branch->id)
                        ->view('filament.forms.components.branch-name-link')
                        ->viewData([
                            'branchName' => $branch->branch_name,
                            'branchId' => $branch->id,
                            'providerName' => $branch->provider?->name ?? null,
                            'providerComment' => $branch->provider?->comment ?? null,
                        ])
                        ->columnSpan(2),
                    \Filament\Forms\Components\Placeholder::make("priority_{$branch->id}")->label('')->content($branch->priority ?? 'N/A')->columnSpan(1),
                    \Filament\Forms\Components\Placeholder::make("cost_{$branch->id}")
                        ->label('')
                        ->content(function () use ($branch) {
                            if ($this->record && $this->record->service_type_id) {
                                $service = $branch->services()->where('service_type_id', $this->record->service_type_id)->first();
                                if ($service && $service->pivot->min_cost) {
                                    return '€' . number_format($service->pivot->min_cost, 2);
                                }
                            }
                            return 'N/A';
                        })
                        ->columnSpan(1),
                    \Filament\Forms\Components\Placeholder::make("communication_{$branch->id}")->label('')->content($branch->communication_method ?? 'N/A')->columnSpan(1),
                    \Filament\Forms\Components\View::make('contact_' . $branch->id)
                        ->view('filament.forms.components.contact-info')
                        ->viewData(['contactInfo' => $this->getBranchContactInfo($branch), 'branchId' => $branch->id])
                        ->columnSpan(1),
                    \Filament\Forms\Components\View::make('phone_' . $branch->id)
                        ->view('filament.forms.components.copiable-field')
                        ->viewData(['label' => 'phone', 'value' => $branch->phone ?? ($branch->getPrimaryPhoneAttribute() ?? 'N/A')])
                        ->columnSpan(1),
                    \Filament\Forms\Components\View::make('address_' . $branch->id)
                        ->view('filament.forms.components.copiable-field')
                        ->viewData(['label' => 'address', 'value' => $branch->address ?? 'N/A'])
                        ->columnSpan(1),
                    \Filament\Forms\Components\View::make('website_' . $branch->id)
                        ->view('filament.forms.components.copiable-field')
                        ->viewData(['label' => 'website', 'value' => $branch->website ?? 'N/A'])
                        ->columnSpan(1),
                    \Filament\Forms\Components\Placeholder::make('distance_' . $branch->id)->label('')->content('N/A')->columnSpan(1),
                    \Filament\Forms\Components\View::make('request_' . $branch->id)
                        ->view('filament.forms.components.request-appointment')
                        ->viewData([
                            'branch' => $branch,
                            'record' => $this->record,
                            'appointmentText' => $this->formatAppointmentRequestText($branch),
                        ])
                        ->columnSpan(1),
                ])
                ->extraAttributes(['class' => 'border-b border-gray-100 hover:bg-gray-50']);
        }
        return $rows;
    }

    protected function getDisplayedProviderBranchesForRequest($cityFilter = null, ?int $limit = null): \Illuminate\Support\Collection
    {
        $limit = $limit ?? static::$requestAppointmentBranchesLimit;
        $branches = $this->getEligibleProviderBranches($this->record, $cityFilter);
        $branchesWithSortData = $branches->map(function ($branch) {
            $distanceData = $this->calculateBranchDistanceForSorting($branch);
            $branch->sort_distance = $distanceData['sort_value'];
            $branch->distance_display = $distanceData['display'];
            $branch->sort_service_type = $this->record->service_type_id ?? 999;
            $branch->sort_status = $branch->status === 'Active' ? 1 : 2;
            return $branch;
        });
        return $branchesWithSortData->sortBy([
            ['sort_distance', 'asc'],
            ['sort_service_type', 'asc'],
            ['sort_status', 'asc'],
        ])->values()->take($limit);
    }

    protected function getEligibleProviderBranches($record, $cityId = null)
    {
        $serviceTypeId = $record->service_type_id;
        $filterCityId = $cityId ?? $record->city_id;
        if ($record->service_type_id == 2) {
            return \App\Models\ProviderBranch::query()
                ->where('status', 'Active')
                ->whereHas('services', fn ($q) => $q->where('service_type_id', $serviceTypeId))
                ->with(['provider', 'city', 'services', 'gopContact', 'operationContact'])
                ->get();
        }
        if (!$record->country_id) {
            return \App\Models\ProviderBranch::query()
                ->where('status', 'Active')
                ->whereHas('services', fn ($q) => $q->where('service_type_id', $serviceTypeId))
                ->with(['provider', 'city', 'services', 'gopContact', 'operationContact'])
                ->get();
        }
        $query = \App\Models\ProviderBranch::query()
            ->where('status', 'Active')
            ->whereHas('services', fn ($q) => $q->where('service_type_id', $serviceTypeId))
            ->whereHas('provider', fn ($q) => $q->where('country_id', $record->country_id))
            ->with(['provider', 'city', 'services', 'gopContact', 'operationContact']);
        if ($filterCityId) {
            $query->where(fn ($q) => $q->where('all_country', true)->orWhereHas('cities', fn ($q2) => $q2->where('cities.id', $filterCityId)));
        }
        return $query->get();
    }

    protected function getBranchContactInfo($branch): string
    {
        $hasEmail = !empty($branch->email);
        $hasPhone = !empty($branch->phone);
        if ($hasEmail && $hasPhone) {
            return 'Email, <button type="button" class="text-blue-600 cursor-pointer hover:underline" wire:click="showPhoneNotification(\'' . addslashes($branch->phone) . '\', \'' . addslashes($branch->branch_name) . '\')">Phone</button>';
        }
        if ($hasEmail) {
            return 'Email';
        }
        if ($hasPhone) {
            return '<button type="button" class="text-blue-600 cursor-pointer hover:underline" wire:click="showPhoneNotification(\'' . addslashes($branch->phone) . '\', \'' . addslashes($branch->branch_name) . '\')">Phone</button>';
        }
        return 'None';
    }

    protected function calculateBranchDistanceForSorting($branch): array
    {
        if (!$this->record || !$this->record->address) {
            return ['sort_value' => 999999, 'display' => '<span class="text-gray-400 text-sm">No file address</span>'];
        }
        $branchAddress = $branch->address ?? $branch->operationContact?->address ?? null;
        if (!$branchAddress) {
            return ['sort_value' => 999999, 'display' => '<span class="text-gray-400 text-sm">No branch address</span>'];
        }
        try {
            $distanceService = new \App\Services\DistanceCalculationService();
            $drivingDistance = $distanceService->calculateDistance($this->record->address, $branchAddress, 'driving');
            if ($drivingDistance) {
                $minutes = $drivingDistance['duration_minutes'] ?? (isset($drivingDistance['duration_seconds']) ? round($drivingDistance['duration_seconds'] / 60, 1) : null);
                if ($minutes !== null) {
                    return ['sort_value' => $minutes, 'display' => $minutes . ' min by car'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return ['sort_value' => 999999, 'display' => '<span class="text-gray-400 text-sm">N/A</span>'];
    }

    protected function formatAppointmentRequestText($branch): string
    {
        $address = $branch->address ?? 'N/A';
        $distanceData = $this->calculateBranchDistanceForSorting($branch);
        $distanceText = $distanceData['sort_value'] < 999999 ? round($distanceData['sort_value'], 0) . 'Mins by car' : 'N/A';
        $branchName = $branch->branch_name ?? 'N/A';
        $dateTime = 'N/A';
        if ($this->record->service_date) {
            $parts = [$this->record->service_date->format('d/m/Y')];
            if ($this->record->service_time) {
                $parts[] = \Carbon\Carbon::parse($this->record->service_time)->format('H:i');
            }
            $dateTime = implode(' at ', $parts);
        }
        $cost = 'N/A';
        $gop = 'N/A';
        $serviceTypeId = $this->record->service_type_id;
        if ($serviceTypeId) {
            $service = $branch->services()->where('service_type_id', $serviceTypeId)->first();
            if ($service) {
                $minCost = $service->pivot->min_cost;
                $maxCost = $service->pivot->max_cost;
                $fileFeeAmount = $this->getFileFeeForServiceType($serviceTypeId);
                if ($serviceTypeId == 2 && $fileFeeAmount) {
                    $cost = number_format($fileFeeAmount, 0) . '€';
                    $gop = $cost;
                } elseif ($serviceTypeId == 1 && ($minCost || $maxCost)) {
                    $base = $minCost ?? $maxCost ?? 0;
                    $rounded = $base < 200 ? 300 : ceil($base / 100) * 100;
                    $cost = number_format($rounded, 0) . '€';
                    $gop = $cost;
                } elseif ($fileFeeAmount) {
                    $max = $maxCost ?? $minCost ?? 0;
                    $mult = ceil($max / 250);
                    $fee = $fileFeeAmount * $mult;
                    $cost = number_format($max, 0) . '€';
                    $gop = number_format($max + $fee, 0) . '€';
                } elseif ($minCost) {
                    $cost = number_format($minCost, 0) . '€';
                    $gop = $cost;
                }
            }
        }
        return "Address: {$address}\nDistance: {$distanceText}\nName: {$branchName}\nDate & Time: {$dateTime}\nCost: {$cost}\nRequested GOP: {$gop}";
    }

    protected function getFileFeeForServiceType(?int $serviceTypeId): ?float
    {
        if (!$serviceTypeId || !$this->record) {
            return null;
        }
        $countryId = $this->record->country_id;
        $cityId = $this->record->city_id;
        if ($countryId && $cityId) {
            $fileFee = FileFee::where('service_type_id', $serviceTypeId)->where('country_id', $countryId)->where('city_id', $cityId)->first();
            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }
        if ($countryId) {
            $fileFee = FileFee::where('service_type_id', $serviceTypeId)->where('country_id', $countryId)->whereNull('city_id')->first();
            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }
        $fileFee = FileFee::where('service_type_id', $serviceTypeId)->whereNull('country_id')->whereNull('city_id')->first();
        return $fileFee ? (float) $fileFee->amount : null;
    }

    protected function sendAppointmentRequestsFromModal(array $data, $record): void
    {
        $selectedBranchIds = $data['selected_branches'] ?? [];
        $customEmails = collect($data['custom_emails'] ?? [])->pluck('email')->filter();
        if (empty($selectedBranchIds) && $customEmails->isNotEmpty()) {
            try {
                Mail::send(new \App\Mail\AppointmentRequestMailable($record, null, $customEmails->toArray()));
                Notification::make()->title('Appointment Request Sent')->body('Successfully sent to ' . $customEmails->count() . ' custom email recipients')->success()->send();
                return;
            } catch (\Exception $e) {
                Log::error('Failed to send appointment request to custom emails', ['error' => $e->getMessage()]);
                Notification::make()->title('Failed to Send')->body('Failed to send appointment request to custom emails.')->danger()->send();
                return;
            }
        }
        if (empty($selectedBranchIds)) {
            Notification::make()->title('No Recipients Selected')->body('Please select at least one provider branch or add custom email recipients.')->warning()->send();
            return;
        }
        $successCount = 0;
        $failureCount = 0;
        $branches = \App\Models\ProviderBranch::whereIn('id', $selectedBranchIds)->get();
        foreach ($branches as $branch) {
            try {
                $hasBranchEmail = !empty($branch->email);
                $hasCustomEmails = $customEmails->isNotEmpty();
                if (!$hasBranchEmail && !$hasCustomEmails) {
                    $this->createManualFollowUpTaskForBranch($branch, $record);
                    $failureCount++;
                    continue;
                }
                Mail::send(new \App\Mail\AppointmentRequestMailable($record, $branch, $customEmails->toArray()));
                $successCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send appointment request', ['branch_id' => $branch->id, 'error' => $e->getMessage()]);
                $failureCount++;
            }
        }
        if ($successCount > 0) {
            Notification::make()->title('Appointment Requests Sent')->body("Successfully sent to {$successCount} providers")->success()->send();
        }
        if ($failureCount > 0) {
            Notification::make()->title('Some Requests Failed')->body("Failed to send to {$failureCount} providers")->warning()->send();
        }
    }

    protected function createManualFollowUpTaskForBranch($branch, $record): void
    {
        Task::create([
            'title' => 'Manual follow-up required for appointment request',
            'description' => "File: {$record->mga_reference} - Patient: {$record->patient->name} - Branch: {$branch->branch_name}",
            'taskable_type' => \App\Models\ProviderBranch::class,
            'taskable_id' => $branch->id,
            'user_id' => Auth::id(),
            'file_id' => $record->id,
            'department' => 'Operation',
            'due_date' => now()->addDay(),
        ]);
    }

    public function showPhoneNotification($phoneNumber, $branchName): void
    {
        Notification::make()
            ->title("{$branchName}'s Phone Number")
            ->body("Phone: {$phoneNumber}")
            ->success()
            ->persistent()
            ->send();
    }
}
