<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Jobs\SendAppointmentRequestsJob;
use App\Models\FileFee;
use App\Models\Gop;
use App\Models\ServiceType;
use App\Services\DistanceCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Full-page "Request Appointment" view for a file.
 * Reuses the same form and logic as the previous slide-over modal.
 */
class RequestAppointment extends EditRecord
{
    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.resources.file-resource.pages.request-appointment';

    protected static ?string $title = 'Request Appointment';

    /** @var array<string, \Illuminate\Support\Collection> */
    protected array $displayedBranchesCache = [];

    /** @var array<int, array{sort_value: float|int, display: string}> */
    protected array $branchDistanceCache = [];

    protected ?float $cachedFileFeeAmount = null;

    protected bool $fileFeeResolved = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing('serviceType');
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
                    ->key(fn (Get $get) => 'branches-' . ($get('city_filter') ?? 'default'))
                    ->schema(fn (Get $get): array => [
                        Grid::make(12)
                            ->schema([
                                Checkbox::make('select_all_branches')
                                    ->label('')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $cityFilter = filled($get('city_filter')) ? (int) $get('city_filter') : null;
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
                        ...$this->getBranchRows(filled($get('city_filter')) ? (int) $get('city_filter') : null),
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

    public function sendRequest(array $confirmationData = []): void
    {
        $this->authorizeAccess();
        $data = $this->form->getState();

        $selectedBranchIds = $data['selected_branches'] ?? [];
        $customEmails = collect($data['custom_emails'] ?? [])->pluck('email')->filter();
        if (empty($selectedBranchIds) && $customEmails->isEmpty()) {
            Notification::make()
                ->title('No Recipients Selected')
                ->body('Please select at least one provider branch or add custom email recipients.')
                ->warning()
                ->send();

            return;
        }

        $createdGop = $this->createGopIfRequested($confirmationData);

        SendAppointmentRequestsJob::dispatchAfterResponse(
            $this->getRecord()->id,
            $data,
            Auth::id(),
            $createdGop?->id,
        );

        Notification::make()
            ->title('Sending Appointment Requests')
            ->body('Your appointment requests are being sent. You will be notified when complete.')
            ->success()
            ->send();

        $this->redirect(FileResource::getUrl('view', ['record' => $this->getRecord()]), navigate: true);
    }

    protected function getFormActions(): array
    {
        $hasExistingOutGop = $this->hasExistingOutGop();

        return [
            Action::make('send')
                ->label('Send Appointment Requests')
                ->modalHeading('Confirm Appointment Request')
                ->modalDescription($hasExistingOutGop
                    ? 'Choose whether to include GOP, then select an existing Out GOP or create a new Out GOP.'
                    : 'Choose whether to include GOP. No Out GOP exists yet, so you can create a new Out GOP.')
                ->modalSubmitActionLabel('Confirm & Send')
                ->form([
                    Checkbox::make('send_gop')
                        ->label('Send GOP during this request')
                        ->default(false)
                        ->live(),
                    Radio::make('gop_action')
                        ->label('GOP Option')
                        ->options($hasExistingOutGop
                            ? [
                                'use_existing' => 'Use existing Out GOP',
                                'create_new' => 'Create new Out GOP',
                            ]
                            : [
                                'create_new' => 'Create new Out GOP',
                            ])
                        ->default($hasExistingOutGop ? 'use_existing' : 'create_new')
                        ->required(fn (Get $get) => (bool) $get('send_gop'))
                        ->live()
                        ->visible(fn (Get $get) => (bool) $get('send_gop')),
                    Select::make('existing_gop_id')
                        ->label('Choose Existing Out GOP')
                        ->options(fn () => $this->getOutGopOptions())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get) => (bool) $get('send_gop') && $get('gop_action') === 'use_existing')
                        ->visible(fn (Get $get) => (bool) $get('send_gop') && $get('gop_action') === 'use_existing'),
                    Grid::make(3)
                        ->schema([
                            TextInput::make('gop_amount')
                                ->label('GOP Amount')
                                ->numeric()
                                ->minValue(0.01)
                                ->required(fn (Get $get) => (bool) $get('send_gop') && $get('gop_action') === 'create_new')
                                ->suffix('EUR'),
                            DatePicker::make('gop_date')
                                ->label('GOP Date')
                                ->native(false)
                                ->default(now()->toDateString())
                                ->required(fn (Get $get) => (bool) $get('send_gop') && $get('gop_action') === 'create_new'),
                        ])
                        ->visible(fn (Get $get) => (bool) $get('send_gop') && $get('gop_action') === 'create_new'),
                ])
                ->action(function (array $data): void {
                    $this->sendRequest($data);
                })
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

    protected function getBranchRows(?int $cityFilter = null): array
    {
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
                            $displayedBranches = $this->getDisplayedProviderBranchesForRequest(
                                filled($get('city_filter')) ? (int) $get('city_filter') : null
                            );
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
                            $service = $this->getBranchServiceForRecord($branch);
                            if ($service && $service->pivot->min_cost) {
                                return '€' . number_format($service->pivot->min_cost, 2);
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
        $cacheKey = filled($cityFilter) ? (string) (int) $cityFilter : 'default';

        if (isset($this->displayedBranchesCache[$cacheKey])) {
            $cached = $this->displayedBranchesCache[$cacheKey];

            return $limit ? $cached->take($limit) : $cached;
        }

        $branches = $this->getEligibleProviderBranches($this->record, $cityFilter);
        $this->warmBranchDistanceCache($branches);

        $branchesWithSortData = $branches->map(function ($branch) {
            $distanceData = $this->calculateBranchDistanceForSorting($branch);
            $branch->sort_distance = $distanceData['sort_value'];
            $branch->distance_display = $distanceData['display'];
            $branch->sort_service_type = $this->record->service_type_id ?? 999;
            $branch->sort_status = $branch->status === 'Active' ? 1 : 2;

            return $branch;
        });
        $sortedBranches = $branchesWithSortData->sortBy([
            ['sort_distance', 'asc'],
            ['sort_service_type', 'asc'],
            ['sort_status', 'asc'],
        ])->values();

        $this->displayedBranchesCache[$cacheKey] = $sortedBranches;

        return $limit ? $sortedBranches->take($limit) : $sortedBranches;
    }

    protected function getEligibleProviderBranches($record, $cityId = null)
    {
        $filterCityId = filled($cityId) ? (int) $cityId : $record->city_id;

        return \App\Models\ProviderBranch::query()
            ->eligibleForFile($record->service_type_id, $record->country_id, $filterCityId)
            ->with(['provider', 'city', 'services', 'gopContact', 'operationContact'])
            ->get();
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

    protected function warmBranchDistanceCache(\Illuminate\Support\Collection $branches): void
    {
        if (! $this->record || ! $this->record->address) {
            foreach ($branches as $branch) {
                $this->branchDistanceCache[$branch->id] = [
                    'sort_value' => 999999,
                    'display' => '<span class="text-gray-400 text-sm">No file address</span>',
                ];
            }

            return;
        }

        $destinations = [];
        foreach ($branches as $branch) {
            if (isset($this->branchDistanceCache[$branch->id])) {
                continue;
            }

            $branchAddress = $branch->address ?? $branch->operationContact?->address;
            if (! $branchAddress) {
                $this->branchDistanceCache[$branch->id] = [
                    'sort_value' => 999999,
                    'display' => '<span class="text-gray-400 text-sm">No branch address</span>',
                ];
                continue;
            }

            $destinations[$branch->id] = $branchAddress;
        }

        if ($destinations === []) {
            return;
        }

        $distanceService = app(DistanceCalculationService::class);
        $results = $distanceService->calculateDistancesBatch($this->record->address, $destinations, 'driving');

        foreach ($destinations as $branchId => $branchAddress) {
            if (isset($this->branchDistanceCache[$branchId])) {
                continue;
            }

            $drivingDistance = $results[$branchId] ?? null;
            if ($drivingDistance) {
                $minutes = $drivingDistance['duration_minutes']
                    ?? (isset($drivingDistance['duration_seconds']) ? round($drivingDistance['duration_seconds'] / 60, 1) : null);

                if ($minutes !== null) {
                    $this->branchDistanceCache[$branchId] = [
                        'sort_value' => $minutes,
                        'display' => $minutes . ' min by car',
                    ];
                    continue;
                }
            }

            $this->branchDistanceCache[$branchId] = [
                'sort_value' => 999999,
                'display' => '<span class="text-gray-400 text-sm">N/A</span>',
            ];
        }
    }

    protected function getBranchServiceForRecord($branch): ?ServiceType
    {
        $serviceTypeId = $this->record?->service_type_id;
        if (! $serviceTypeId) {
            return null;
        }

        return $branch->services->firstWhere('id', $serviceTypeId);
    }

    protected function calculateBranchDistanceForSorting($branch): array
    {
        if (isset($this->branchDistanceCache[$branch->id])) {
            return $this->branchDistanceCache[$branch->id];
        }

        if (! $this->record || ! $this->record->address) {
            return [
                'sort_value' => 999999,
                'display' => '<span class="text-gray-400 text-sm">No file address</span>',
            ];
        }

        $branchAddress = $branch->address ?? $branch->operationContact?->address ?? null;
        if (! $branchAddress) {
            return [
                'sort_value' => 999999,
                'display' => '<span class="text-gray-400 text-sm">No branch address</span>',
            ];
        }

        try {
            $distanceService = app(DistanceCalculationService::class);
            $drivingDistance = $distanceService->calculateDistance($this->record->address, $branchAddress, 'driving');
            if ($drivingDistance) {
                $minutes = $drivingDistance['duration_minutes']
                    ?? (isset($drivingDistance['duration_seconds']) ? round($drivingDistance['duration_seconds'] / 60, 1) : null);
                if ($minutes !== null) {
                    return $this->branchDistanceCache[$branch->id] = [
                        'sort_value' => $minutes,
                        'display' => $minutes . ' min by car',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $this->branchDistanceCache[$branch->id] = [
            'sort_value' => 999999,
            'display' => '<span class="text-gray-400 text-sm">N/A</span>',
        ];
    }

    protected function formatAppointmentRequestText($branch): string
    {
        $address = $branch->address ?? 'N/A';
        $patientAddress = $this->record->address ?? null;
        $distanceText = isset($branch->sort_distance) && $branch->sort_distance < 999999
            ? round($branch->sort_distance, 0) . 'Mins by car'
            : 'N/A';
        $branchName = $branch->branch_name ?? 'N/A';
        $serviceTypeName = trim((string) ($this->record->serviceType?->name ?? ''));
        $isHospitalVisit = strcasecmp($serviceTypeName, 'Hospital Visit') === 0;
        $dateTime = $isHospitalVisit
            ? 'The patient will wait in the ER for assesment'
            : 'N/A';
        if (! $isHospitalVisit && $this->record->service_date) {
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
            $service = $this->getBranchServiceForRecord($branch);
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
        $text = "Address: {$address}\n";
        $text .= "Distance: {$distanceText}\n";
        $text .= "Name: {$branchName}\n";

        if (!empty($patientAddress)) {
            $text .= "Patient Address: {$patientAddress}\n";
        }

        $text .= "Date & Time: {$dateTime}\n";
        $text .= "Cost: {$cost}\n";
        $text .= "Requested GOP: {$gop}";

        return $text;
    }

    protected function getFileFeeForServiceType(?int $serviceTypeId): ?float
    {
        if ($this->fileFeeResolved) {
            return $this->cachedFileFeeAmount;
        }

        $this->fileFeeResolved = true;

        if (! $serviceTypeId || ! $this->record) {
            return $this->cachedFileFeeAmount = null;
        }

        $countryId = $this->record->country_id;
        $cityId = $this->record->city_id;
        if ($countryId && $cityId) {
            $fileFee = FileFee::where('service_type_id', $serviceTypeId)->where('country_id', $countryId)->where('city_id', $cityId)->first();
            if ($fileFee) {
                return $this->cachedFileFeeAmount = (float) $fileFee->amount;
            }
        }
        if ($countryId) {
            $fileFee = FileFee::where('service_type_id', $serviceTypeId)->where('country_id', $countryId)->whereNull('city_id')->first();
            if ($fileFee) {
                return $this->cachedFileFeeAmount = (float) $fileFee->amount;
            }
        }
        $fileFee = FileFee::where('service_type_id', $serviceTypeId)->whereNull('country_id')->whereNull('city_id')->first();

        return $this->cachedFileFeeAmount = $fileFee ? (float) $fileFee->amount : null;
    }

    protected function createGopIfRequested(array $confirmationData): ?Gop
    {
        if (!($confirmationData['send_gop'] ?? false)) {
            return null;
        }

        $record = $this->getRecord();
        $gopAction = $confirmationData['gop_action'] ?? ($this->hasExistingOutGop() ? 'use_existing' : 'create_new');

        if ($gopAction === 'use_existing') {
            $selectedGopId = $confirmationData['existing_gop_id'] ?? null;
            $existingOutGop = $record->gops()
                ->where('type', 'Out')
                ->whereKey($selectedGopId)
                ->first();

            if (!$existingOutGop) {
                Notification::make()
                    ->title('GOP Not Found')
                    ->body('Please choose a valid Out GOP from the list or create a new Out GOP.')
                    ->danger()
                    ->send();

                return null;
            }

            return $existingOutGop;
        }

        $gop = Gop::create([
            'file_id' => $record->id,
            'type' => 'Out',
            'amount' => (float) $confirmationData['gop_amount'],
            'date' => $confirmationData['gop_date'],
            'status' => 'Not Sent',
        ]);

        // GOP is attached to appointment request emails in the background job.
        $record->unsetRelation('gops');

        return $gop;
    }

    protected function hasExistingOutGop(): bool
    {
        return $this->getRecord()
            ->gops()
            ->where('type', 'Out')
            ->exists();
    }

    protected function getLatestOutGopForRecord(): ?Gop
    {
        return $this->getRecord()
            ->gops()
            ->where('type', 'Out')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    protected function getOutGopOptions(): array
    {
        return $this->getRecord()
            ->gops()
            ->where('type', 'Out')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function (Gop $gop) {
                $date = $gop->date ? $gop->date->format('d/m/Y') : 'N/A';
                $label = "{$date} - {$gop->amount} EUR (ID #{$gop->id})";

                return [$gop->id => $label];
            })
            ->toArray();
    }

    public function copyToClipboard($text, $label): void
    {
        Notification::make()
            ->title('Copied to clipboard')
            ->body("'{$label}' has been copied to your clipboard")
            ->success()
            ->send();
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
