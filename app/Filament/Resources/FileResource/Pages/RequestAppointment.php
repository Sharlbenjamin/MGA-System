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

    /** @var array<int, array{sort_value: float|int, display: string}> */
    protected array $branchDistanceCache = [];

    protected ?float $cachedFileFeeAmount = null;

    protected bool $fileFeeResolved = false;

    /** @var array<int, array<string, mixed>> */
    public array $branchTableRows = [];

    public array $selectedBranchIds = [];

    public bool $selectAllBranches = false;

    public bool $distancesLoading = false;

    public bool $distancesLoaded = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing('serviceType');
        $this->authorizeAccess();
        $this->fillForm();
        $this->prepareBranchTable(filled($this->record->city_id) ? (int) $this->record->city_id : null);
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
                            ->afterStateUpdated(function ($state) {
                                $this->selectedBranchIds = [];
                                $this->selectAllBranches = false;
                                $this->refreshBranchTable(filled($state) ? (int) $state : null);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Available Branches')
                    ->description('Select the provider branches you want to send appointment requests to')
                    ->schema([
                        \Filament\Forms\Components\View::make('filament.resources.file-resource.components.request-appointment-branches')
                            ->viewData(fn (): array => ['livewire' => $this]),
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
            'custom_emails' => [],
        ]);
    }

    public function loadBranchDistances(): void
    {
        if ($this->distancesLoaded || $this->distancesLoading) {
            return;
        }

        if (! (bool) config('services.google.distance_enabled', true) || ! $this->record?->address) {
            $this->distancesLoaded = true;

            return;
        }

        $this->distancesLoading = true;

        $cityFilter = filled($this->data['city_filter'] ?? null)
            ? (int) $this->data['city_filter']
            : (filled($this->record->city_id) ? (int) $this->record->city_id : null);
        $branches = $this->getEligibleProviderBranches($this->record, $cityFilter);
        $this->warmBranchDistanceCache($branches);

        $sortedBranches = $branches
            ->map(function ($branch) {
                $distanceData = $this->calculateBranchDistanceForSorting($branch);
                $branch->sort_distance = $distanceData['sort_value'];

                return $branch;
            })
            ->sortBy([
                ['sort_distance', 'asc'],
                ['priority', 'asc'],
            ])
            ->values();

        $this->branchTableRows = $sortedBranches
            ->map(fn ($branch) => $this->buildBranchTableRow($branch))
            ->all();

        $this->distancesLoading = false;
        $this->distancesLoaded = true;
        $this->syncSelectAllState();
    }

    public function refreshBranchTable(?int $cityFilter = null): void
    {
        $this->distancesLoaded = false;
        $this->distancesLoading = false;
        $this->branchDistanceCache = [];
        $this->prepareBranchTable($cityFilter);
    }

    public function toggleSelectAll(bool $selected): void
    {
        $this->selectAllBranches = $selected;
        $this->selectedBranchIds = $selected
            ? collect($this->branchTableRows)->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    public function updatedSelectedBranchIds(): void
    {
        $this->syncSelectAllState();
    }

    protected function syncSelectAllState(): void
    {
        $total = count($this->branchTableRows);
        $selected = count($this->selectedBranchIds);
        $this->selectAllBranches = $total > 0 && $selected === $total;
    }

    protected function prepareBranchTable(?int $cityFilter = null): void
    {
        $this->getFileFeeForServiceType($this->record->service_type_id);

        $branches = $this->getEligibleProviderBranches($this->record, $cityFilter)
            ->sortBy([
                fn ($branch) => $branch->priority ?? 999,
                fn ($branch) => $branch->status === 'Active' ? 0 : 1,
            ])
            ->values();

        $this->branchTableRows = $branches
            ->map(fn ($branch) => $this->buildBranchTableRow($branch))
            ->all();
    }

    protected function buildBranchTableRow($branch): array
    {
        $service = $this->getBranchServiceForRecord($branch);
        $cost = 'N/A';
        if ($service && $service->pivot->min_cost) {
            $cost = '€' . number_format($service->pivot->min_cost, 2);
        }

        $branch->sort_distance = $this->branchDistanceCache[$branch->id]['sort_value'] ?? 999999;

        return [
            'id' => $branch->id,
            'branch_name' => $branch->branch_name,
            'provider_name' => $branch->provider?->name,
            'provider_comment' => $branch->provider?->comment,
            'priority' => $branch->priority ?? 'N/A',
            'cost' => $cost,
            'communication_method' => $branch->communication_method ?? 'N/A',
            'contact_html' => $this->getBranchContactInfo($branch),
            'phone' => $branch->phone ?? ($branch->getPrimaryPhoneAttribute() ?? 'N/A'),
            'address' => $branch->address ?? 'N/A',
            'website' => $branch->website ?? 'N/A',
            'appointment_text' => $this->formatAppointmentRequestText($branch),
        ];
    }

    public function sendRequest(array $confirmationData = []): void
    {
        $this->authorizeAccess();
        $data = $this->form->getState();

        $selectedBranchIds = array_map('intval', $this->selectedBranchIds);
        $data['selected_branches'] = $selectedBranchIds;
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

    protected function getEligibleProviderBranches($record, $cityId = null)
    {
        $filterCityId = filled($cityId) ? (int) $cityId : $record->city_id;
        $serviceTypeId = $record->service_type_id;

        return \App\Models\ProviderBranch::query()
            ->eligibleForFile($serviceTypeId, $record->country_id, $filterCityId)
            ->with([
                'provider:id,name,comment,country_id',
                'city:id,name',
                'gopContact:id,address',
                'operationContact:id,address',
                'services' => fn ($query) => $query->when(
                    $serviceTypeId,
                    fn ($serviceQuery) => $serviceQuery->where('service_types.id', $serviceTypeId),
                ),
            ])
            ->orderBy('priority')
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
