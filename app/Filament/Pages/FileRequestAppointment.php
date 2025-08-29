<?php

namespace App\Filament\Pages;

use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\City;
use App\Services\DistanceCalculationService;
use App\Mail\AppointmentNotificationMail;
use Filament\Pages\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ActionColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Grid as InfolistGrid;

class FileRequestAppointment extends Page implements HasTable
{
    use InteractsWithTable, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $title = 'Request Appointment';
    protected static ?string $slug = 'file-request-appointment';
    protected static ?string $navigationGroup = 'Operation';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];
    public $record;
    public $file;
    public $customEmails = [];

    protected $queryString = ['record'];

    public function mount($record = null): void
    {
        $this->record = $record;
        $this->file = File::with(['patient', 'city', 'country', 'serviceType'])
            ->findOrFail($record);
        
        // Check authorization
        if (!auth()->user()->can('view', $this->file)) {
            abort(403);
        }

        $this->form->fill([
            'custom_emails' => [],
        ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->file)
            ->schema([
                InfolistSection::make('File Information')
                    ->schema([
                        InfolistGrid::make(3)
                            ->schema([
                                TextEntry::make('mga_reference')
                                    ->label('File Reference')
                                    ->color('warning')
                                    ->weight('bold')
                                    ->size('lg'),
                                TextEntry::make('patient.name')
                                    ->label('Patient Name')
                                    ->color('danger')
                                    ->weight('bold'),
                                TextEntry::make('serviceType.name')
                                    ->label('Service Type')
                                    ->color('info')
                                    ->weight('bold'),
                                TextEntry::make('country.name')
                                    ->label('Country'),
                                TextEntry::make('city.name')
                                    ->label('City'),
                                TextEntry::make('address')
                                    ->label('Address'),
                                TextEntry::make('symptoms')
                                    ->label('Symptoms')
                                    ->columnSpan(2),
                                TextEntry::make('service_datetime')
                                    ->label('Service Date & Time')
                                    ->formatStateUsing(function ($state) {
                                        if ($this->file->service_date && $this->file->service_time) {
                                            return $this->file->service_date->format('Y-m-d') . ' ' . $this->file->service_time;
                                        }
                                        return 'N/A';
                                    }),
                            ])
                    ])
                    ->collapsible()
                    ->collapsed(false)
            ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Additional Email Addresses')
                ->description('Add custom email addresses to include in appointment requests')
                ->schema([
                    Repeater::make('custom_emails')
                        ->schema([
                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->placeholder('Enter email address')
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add Email')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['email'] ?? null)
                        ->live()
                ])
        ]);
    }

    public function save(): void
    {
        $this->data = $this->form->getState();
        
        Notification::make()
            ->title('Email addresses saved')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getProviderBranchesQuery())
            ->columns([
                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (ProviderBranch $record): string => route('filament.admin.resources.provider-branches.edit', $record))
                    ->color('primary')
                    ->weight('bold'),
                
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('services')
                    ->label('Services')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        $services = $record->branchServices()
                            ->where('service_type_id', $this->file->service_type_id)
                            ->where('is_active', true)
                            ->get()
                            ->pluck('serviceType.name')
                            ->implode(', ');
                        
                        return $services ?: 'No services available';
                    })
                    ->html()
                    ->wrap(),
                
                TextColumn::make('cost')
                    ->label('Cost')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        $service = $record->branchServices()
                            ->where('service_type_id', $this->file->service_type_id)
                            ->where('is_active', true)
                            ->first();
                        
                        return $service ? '$' . number_format($service->cost, 2) : 'N/A';
                    })
                    ->alignEnd(),
                
                TextColumn::make('distance')
                    ->label('Distance')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        return $this->calculateDistance($record);
                    })
                    ->alignCenter(),
                
                TextColumn::make('contact_info')
                    ->label('Contact Info')
                    ->formatStateUsing(function (ProviderBranch $record) {
                        $hasEmail = $record->getGopEmail() || $record->getOperationEmail();
                        $hasPhone = $record->getGopPhone() || $record->getOperationPhone();
                        
                        if ($hasEmail && $hasPhone) {
                            return new HtmlString(
                                '<span class="text-green-600">Email, Phone</span>'
                            );
                        } elseif ($hasEmail) {
                            return new HtmlString(
                                '<span class="text-blue-600">Email</span>'
                            );
                        } elseif ($hasPhone) {
                            return new HtmlString(
                                '<span class="text-orange-600">Phone</span>'
                            );
                        }
                        
                        return new HtmlString('<span class="text-gray-500">None</span>');
                    })
                    ->html(),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Inactive',
                    ]),
            ])
            ->filters([
                SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->options(ServiceType::pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['value'], fn (Builder $query, $value): Builder => 
                            $query->whereHas('branchServices', fn ($q) => $q->where('service_type_id', $value))
                        )
                    ),
                
                SelectFilter::make('country')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['value'], fn (Builder $query, $value): Builder => 
                            $query->whereHas('cities', fn ($q) => $q->where('country_id', $value))
                        )
                    ),
                
                SelectFilter::make('city')
                    ->label('City')
                    ->options(fn () => City::where('country_id', $this->file->country_id)->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => 
                        $query->when($data['value'], fn (Builder $query, $value): Builder => 
                            $query->whereHas('cities', fn ($q) => $q->where('city_id', $value))
                        )
                    ),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ]),
                
                Filter::make('has_email')
                    ->label('Has Email')
                    ->query(fn (Builder $query): Builder => 
                        $query->where(function ($q) {
                            $q->whereNotNull('email')
                              ->orWhereHas('gopContact', fn ($subQ) => $subQ->whereNotNull('email'))
                              ->orWhereHas('operationContact', fn ($subQ) => $subQ->whereNotNull('email'));
                        })
                    )
                    ->form([
                        Checkbox::make('has_email')
                            ->label('Has Email Contact')
                    ]),
                
                Filter::make('has_phone')
                    ->label('Has Phone')
                    ->query(fn (Builder $query): Builder => 
                        $query->where(function ($q) {
                            $q->whereNotNull('phone')
                              ->orWhereHas('gopContact', fn ($subQ) => $subQ->whereNotNull('phone_number'))
                              ->orWhereHas('operationContact', fn ($subQ) => $subQ->whereNotNull('phone_number'));
                        })
                    )
                    ->form([
                        Checkbox::make('has_phone')
                            ->label('Has Phone Contact')
                    ]),
            ])
            ->bulkActions([
                BulkAction::make('sendAppointmentRequests')
                    ->label('Send Appointment Requests')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->action(function ($records) {
                        $this->sendAppointmentRequests($records);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Send Appointment Requests')
                    ->modalDescription('Are you sure you want to send appointment requests to the selected provider branches?')
                    ->modalSubmitActionLabel('Send Requests')
            ])
            ->actions([
                TableAction::make('showPhone')
                    ->label('Show Phone')
                    ->icon('heroicon-o-phone')
                    ->action(function (ProviderBranch $record) {
                        $phone = $record->getGopPhone() ?: $record->getOperationPhone();
                        if ($phone) {
                            Notification::make()
                                ->title("Branch {$record->branch_name} phone number")
                                ->body($phone)
                                ->persistent()
                                ->send();
                        }
                    })
                    ->visible(fn (ProviderBranch $record) => $record->getGopPhone() || $record->getOperationPhone())
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('priority', 'asc');
    }

    protected function getProviderBranchesQuery(): Builder
    {
        return ProviderBranch::query()
            ->with(['city', 'branchServices.serviceType', 'gopContact', 'operationContact'])
            ->where('status', 'Active')
            ->whereHas('branchServices', function ($query) {
                $query->where('service_type_id', $this->file->service_type_id)
                      ->where('is_active', true);
            })
            ->where(function ($query) {
                $query->where('all_country', true)
                      ->orWhereHas('cities', function ($subQuery) {
                          $subQuery->where('city_id', $this->file->city_id);
                      });
            });
    }

    protected function calculateDistance(ProviderBranch $branch): string
    {
        if (!$this->file->address || !$branch->address) {
            return 'N/A';
        }

        $distanceService = new DistanceCalculationService();
        $result = $distanceService->calculateDistance(
            $this->file->address,
            $branch->address,
            'driving'
        );

        if ($result) {
            return "{$result['distance']} - {$result['duration']}";
        }

        // Fallback calculation
        return '35 min walking';
    }

    protected function sendAppointmentRequests($records): void
    {
        $successCount = 0;
        $failureCount = 0;
        $customEmails = collect($this->data['custom_emails'] ?? [])->pluck('email')->filter();

        foreach ($records as $branch) {
            try {
                $emails = collect();
                
                // Add branch emails
                if ($branch->getGopEmail()) {
                    $emails->push($branch->getGopEmail());
                }
                if ($branch->getOperationEmail()) {
                    $emails->push($branch->getOperationEmail());
                }
                
                // Add custom emails
                $emails = $emails->merge($customEmails)->unique();
                
                if ($emails->isEmpty()) {
                    // No email available, create task for manual follow-up
                    $this->createManualFollowUpTask($branch);
                    $failureCount++;
                    continue;
                }

                // Send email to each address
                foreach ($emails as $email) {
                    Mail::to($email)->send(new AppointmentNotificationMail('file_created', $this->file));
                }
                
                $successCount++;
                
            } catch (\Exception $e) {
                Log::error('Failed to send appointment request', [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->branch_name,
                    'error' => $e->getMessage()
                ]);
                $failureCount++;
            }
        }

        // Show notification
        if ($successCount > 0) {
            Notification::make()
                ->title('Appointment Requests Sent')
                ->body("✅ Successfully sent to {$successCount} providers")
                ->success()
                ->send();
        }

        if ($failureCount > 0) {
            Notification::make()
                ->title('Some Requests Failed')
                ->body("⚠️ Failed to send to {$failureCount} providers (manual follow-up tasks created)")
                ->warning()
                ->send();
        }
    }

    protected function createManualFollowUpTask(ProviderBranch $branch): void
    {
        // Create a task for manual follow-up
        \App\Models\Task::create([
            'title' => "Manual follow-up required for appointment request",
            'description' => "File: {$this->file->mga_reference} - Patient: {$this->file->patient->name} - Branch: {$branch->branch_name}",
            'taskable_type' => ProviderBranch::class,
            'taskable_id' => $branch->id,
            'assigned_to' => auth()->id(),
            'due_date' => now()->addDays(1),
            'priority' => 'high',
            'status' => 'pending'
        ]);
    }

    public function getTitle(): string
    {
        return "Request Appointment - {$this->file->mga_reference}";
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

    public function render()
    {
        return view('filament.pages.file-request-appointment', [
            'file' => $this->file,
        ]);
    }
}
