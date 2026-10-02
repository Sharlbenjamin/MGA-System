<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\Lead;
use App\Models\Client;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use App\Mail\CustomLeadEmail;
use Illuminate\Support\Facades\Mail;
use App\Models\DraftMail;
use Carbon\Carbon;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeadResource extends Resource
{
    public const DEFAULT_CLIENT_STATUS = 'Interested';

    protected static ?string $model = Lead::class;
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    public static function form(Forms\Form $form): Forms\Form
    {
        $methods = ['Email' => 'Email', 'Phone' => 'Phone', 'Linked In' => 'Linked In', 'Other' => 'Other',];
        
        // Get all available statuses from draft mails for Client type
        $leadStatuses = \App\Filament\Resources\DraftMailResource::getAvailableStatuses('Client');
        
        return $form
            ->schema([
                Section::make('Client Information')
                    ->schema([
                        Toggle::make('create_new_client')
                            ->label('Create New Client')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?bool $state) {
                                $set('client_id', null);
                                $set('new_client_company_name', null);
                                $set('new_client_initials', null);

                                if ($state) {
                                    $set('new_client_type', 'Assistance');
                                    $set('new_client_status', self::DEFAULT_CLIENT_STATUS);

                                    return;
                                }

                                $set('new_client_type', null);
                                $set('new_client_status', null);
                            }),

                        // Existing Client Selection
                        Select::make('client_id')
                            ->label('Select Client')
                            ->options(Client::pluck('company_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => !$get('create_new_client'))
                            ->required(fn (Get $get) => !$get('create_new_client')),

                        // New Client Creation Fields
                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_client_company_name')
                                    ->label('Company Name')
                                    ->required(fn (Get $get) => $get('create_new_client'))
                                    ->visible(fn (Get $get) => $get('create_new_client'))
                                    ->unique('clients', 'company_name', ignoreRecord: true)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        $set('new_client_initials', self::initialsFromCompanyName($state));
                                    }),

                                Select::make('new_client_type')
                                    ->label('Client Type')
                                    ->options([
                                        'Assistance' => 'Assistance',
                                        'Insurance' => 'Insurance',
                                        'Agency' => 'Agency',
                                    ])
                                    ->default('Assistance')
                                    ->required(fn (Get $get) => $get('create_new_client'))
                                    ->visible(fn (Get $get) => $get('create_new_client')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_client')),

                        Grid::make(2)
                            ->schema([
                                Select::make('new_client_status')
                                    ->label('Status')
                                    ->options([
                                        'Searching' => 'Searching',
                                        'Interested' => 'Interested',
                                        'Sent' => 'Sent',
                                        'Rejected' => 'Rejected',
                                        'Active' => 'Active',
                                        'On Hold' => 'On Hold',
                                        'Closed' => 'Closed',
                                        'Broker' => 'Broker',
                                        'No Reply' => 'No Reply',
                                    ])
                                    ->default(self::DEFAULT_CLIENT_STATUS)
                                    ->required(fn (Get $get) => $get('create_new_client'))
                                    ->visible(fn (Get $get) => $get('create_new_client')),

                                TextInput::make('new_client_initials')
                                    ->label('Initials')
                                    ->maxLength(10)
                                    ->required(fn (Get $get) => $get('create_new_client'))
                                    ->visible(fn (Get $get) => $get('create_new_client')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_client')),
                    ])
                    ->collapsible(),

                Section::make('Lead Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required(),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->unique('leads', 'email', ignoreRecord: true)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $email = $get('email');
                                        if ($email) {
                                            $exists = Lead::where('email', $email)->exists();
                                            if ($exists) {
                                                $set('email', null);
                                                Notification::make()
                                                    ->title('Email Already Exists')
                                                    ->body('This email is already registered with another lead.')
                                                    ->danger()
                                                    ->send();
                                            }
                                        }
                                    }),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel(),

                                TextInput::make('linked_in')
                                    ->label('LinkedIn Profile'),
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options($leadStatuses)
                            ->default('Introduction')
                            ->required()
                            ->preload()
                            ->searchable(),

                        Select::make('contact_method')
                            ->label('Contact Method')
                            ->options($methods)
                            ->preload()
                            ->searchable(),

                        DatePicker::make('last_contact_date')
                            ->label('Last Contact Date'),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        $leadStatuses = \App\Filament\Resources\DraftMailResource::getAvailableStatuses('Client');
        $ActionStatuses = ['Introduction','Reminder','Presentation','Price List','Contract',];
        
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('client'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('leads.last_contact_date IS NULL')
                ->orderBy('leads.last_contact_date'))
            ->columns([
                TextColumn::make('client.company_name')
                    ->label('Client')
                    ->sortable()
                    ->searchable()
                    ->url(fn (Lead $record): ?string => $record->client ? ClientResource::getUrl('overview', ['record' => $record->client]) : null),
                TextColumn::make('client.status')
                    ->label('Client Status')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->color(fn (?string $state): string => match ($state) {
                        'Searching' => 'danger',
                        'Interested' => 'warning',
                        'Sent' => 'success',
                        'Rejected' => 'gray',
                        'Active' => 'success',
                        'Black list' => 'danger',
                        'Blacklist' => 'danger',
                        'On Hold' => 'gray',
                        'Broker' => 'success',
                        'No Reply' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('first_name')->sortable()->searchable(),
                TextColumn::make('contact_method')->sortable()->searchable(),
                TextColumn::make('status')->label('Lead Status')->badge()->sortable()->color(fn (string $state): string => match ($state) {
                    'Introduction' => 'warning',
                        'Introduction Sent' => 'info',
                        'Reminder' => 'warning',
                        'Reminder Sent' => 'info',
                        'Presentation' => 'warning',
                        'Presentation Sent' => 'info',
                        'Price List' => 'warning',
                        'Price List Sent' => 'info',
                        'Contract' => 'warning',
                        'Contract Sent' => 'info',
                        'Interested' => 'warning',
                        'Error' => 'danger',
                        'Partner' => 'success',
                        'Rejected' => 'gray',
                        default => 'gray',
            }),
                TextColumn::make('last_contact_date')
                    ->label('Follow-up')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::followUpCountdownLabel($state))
                    ->color(fn ($state): string => self::followUpCountdownColor($state)),
            ])
            ->actions([
                Action::make('viewClient')
                    ->label('View Client')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(function (Lead $record): ?string {
                        if ($record->client === null) {
                            return null;
                        }

                        return ClientResource::getUrl('overview', ['record' => $record->client]);
                    })
                    ->visible(fn (Lead $record): bool => $record->client !== null),
                Action::make('editLead')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->modalHeading('Edit lead')
                    ->modalSubmitActionLabel('Save')
                    ->modalWidth('2xl')
                    ->authorize(fn (Lead $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->fillForm(fn (Lead $record): array => [
                        'client_id' => $record->client_id,
                        'first_name' => $record->first_name,
                        'email' => $record->email,
                        'phone' => $record->phone,
                        'linked_in' => $record->linked_in,
                        'status' => $record->status,
                        'contact_method' => $record->contact_method,
                        'last_contact_date' => $record->last_contact_date?->toDateString(),
                    ])
                    ->form(fn (Lead $record): array => self::editableLeadForm($record))
                    ->action(function (Lead $record, array $data): void {
                        $record->update($data);

                        Notification::make()
                            ->title('Lead updated')
                            ->success()
                            ->send();
                    }),
                Action::make('Send Email')->icon('heroicon-o-paper-airplane')->requiresConfirmation()->action(fn ($record) => self::sendEmails($record))->color('success'),
            ]) ->filters([
                SelectFilter::make('client')
                    ->label('Client')
                    ->relationship(
                        'client',
                        'company_name',
                        fn (Builder $query) => $query->whereHas('leads')->orderBy('company_name'),
                    )
                    ->searchable()
                    ->multiple()
                    ->native(false),
                SelectFilter::make('client_status')
                    ->label('Client Status')
                    ->options(self::clientStatusOptions())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(function (Builder $query, array $data, $livewire): Builder {
                        $selected = array_values(array_filter(
                            (array) ($data['values'] ?? []),
                            fn ($status) => filled($status),
                        ));

                        if ($selected !== []) {
                            return $query->forClientStatuses($selected);
                        }

                        $selectedClients = array_filter((array) data_get($livewire, 'tableFilters.client.values', []));

                        if ($selectedClients !== []) {
                            return $query;
                        }

                        return $query->excludingRejectedClients();
                    }),
                SelectFilter::make('follow_up')
                    ->label('Follow-up')
                    ->options([
                        'overdue' => 'Past due',
                        'due_today' => 'Due today',
                        'remaining' => 'Days remaining',
                        'no_date' => 'No contact date',
                    ])
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $selected = array_values(array_filter((array) ($data['values'] ?? [])));

                        if ($selected === []) {
                            return $query;
                        }

                        $dueOn = Carbon::today()->subWeek()->toDateString();

                        return $query->where(function (Builder $query) use ($selected, $dueOn): void {
                            foreach ($selected as $value) {
                                $query->orWhere(function (Builder $query) use ($value, $dueOn): void {
                                    if ($value === 'overdue') {
                                        $query->whereDate('leads.last_contact_date', '<', $dueOn);
                                    } elseif ($value === 'due_today') {
                                        $query->whereDate('leads.last_contact_date', '=', $dueOn);
                                    } elseif ($value === 'remaining') {
                                        $query->whereDate('leads.last_contact_date', '>', $dueOn);
                                    } elseif ($value === 'no_date') {
                                        $query->whereNull('leads.last_contact_date');
                                    }
                                });
                            }
                        });
                    }),
                Filter::make('needs_action')
                    ->label('Needs Action')
                    ->query(fn (Builder $query): Builder => $query->whereIn('leads.status', $ActionStatuses)),
                SelectFilter::make('status')
                    ->multiple()
                    ->options($leadStatuses)
                    ->label('Lead Status')
                    ->attribute('leads.status'),
            ])->bulkActions([
                BulkAction::make('Send Bulk Emails')->icon('heroicon-o-paper-airplane')->requiresConfirmation()->action(fn ($records) => self::sendEmails($records))->deselectRecordsAfterCompletion()->color('success'),
                    BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-down-on-square-stack')->color('info')
                    ->form([
                Select::make('status')
                    ->label('New Status')
                    ->options(\App\Filament\Resources\DraftMailResource::getAvailableStatuses('Client'))
                    ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each->update(['status' => $data['status']]);
                    })
                    ->deselectRecordsAfterCompletion(), // Optional: Unselect records after action
                    BulkAction::make('send_tailored_mail')->label('Send Tailored Mail')
                ->form([
                    TextInput::make('subject')->label('Email Subject')->required(),
                    Textarea::make('body')->label('Email Body')->required(),
                ])
                ->action(function (ComponentContainer $form, $records) {
                    $subject = $form->getState()['subject'];
                    $body = $form->getState()['body'];

                    foreach ($records as $lead) {
                        Lead::sendTailoredMail([$lead->email], $subject, $body);
                    }

                })
                ->modalHeading('Send Tailored Mail')
                ->modalButton('Send')
                ->icon('heroicon-o-paper-airplane'),
        ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function editableLeadForm(Lead $record): array
    {
        $methods = ['Email' => 'Email', 'Phone' => 'Phone', 'Linked In' => 'Linked In', 'Other' => 'Other'];
        $leadStatuses = \App\Filament\Resources\DraftMailResource::getAvailableStatuses('Client');

        return [
            Select::make('client_id')
                ->label('Client')
                ->options(fn (): array => Client::query()->orderBy('company_name')->pluck('company_name', 'id')->all())
                ->searchable()
                ->preload()
                ->required(),
            Grid::make(2)
                ->schema([
                    TextInput::make('first_name')
                        ->label('First Name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique('leads', 'email', ignorable: $record),
                    TextInput::make('phone')
                        ->label('Phone')
                        ->tel(),
                    TextInput::make('linked_in')
                        ->label('LinkedIn Profile'),
                    Select::make('status')
                        ->label('Status')
                        ->options($leadStatuses)
                        ->required()
                        ->preload()
                        ->searchable(),
                    Select::make('contact_method')
                        ->label('Contact Method')
                        ->options($methods)
                        ->preload()
                        ->searchable(),
                    DatePicker::make('last_contact_date')
                        ->label('Last Contact Date'),
                ]),
        ];
    }

    public static function followUpDaysRemaining(mixed $lastContactDate): ?int
    {
        if (blank($lastContactDate)) {
            return null;
        }

        $dueDate = Carbon::parse($lastContactDate)->startOfDay()->addWeek();

        return (int) round(Carbon::today()->diffInDays($dueDate, false));
    }

    public static function followUpCountdownLabel(mixed $lastContactDate): string
    {
        $days = self::followUpDaysRemaining($lastContactDate);

        if ($days === null) {
            return 'No contact date';
        }

        if ($days === 0) {
            return 'Due today';
        }

        if ($days > 0) {
            return $days === 1 ? '1 day remaining' : "{$days} days remaining";
        }

        $past = abs($days);

        return $past === 1 ? '1 day past' : "{$past} days past";
    }

    public static function followUpCountdownColor(mixed $lastContactDate): string
    {
        $days = self::followUpDaysRemaining($lastContactDate);

        if ($days === null) {
            return 'gray';
        }

        if ($days < 0) {
            return 'danger';
        }

        if ($days <= 2) {
            return 'warning';
        }

        return 'success';
    }

    public static function initialsFromCompanyName(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = [];

        foreach ($words as $word) {
            if (preg_match('/\p{L}|\p{N}/u', $word, $match) !== 1) {
                continue;
            }

            $letters[] = mb_strtoupper($match[0]);
        }

        return mb_substr(implode('', $letters), 0, 10);
    }

    /**
     * @return array<string, string>
     */
    public static function clientStatusOptions(): array
    {
        $options = [
            'Searching' => 'Searching',
            'Interested' => 'Interested',
            'Sent' => 'Sent',
            'Rejected' => 'Rejected',
            'Active' => 'Active',
            'Black list' => 'Black list',
            'On Hold' => 'On Hold',
            'Broker' => 'Broker',
            'No Reply' => 'No Reply',
        ];

        $storedStatuses = Client::query()
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        foreach ($storedStatuses as $status) {
            $status = trim((string) $status);

            if ($status === '') {
                continue;
            }

            $canonical = collect($options)->first(
                fn (string $option): bool => strcasecmp($option, $status) === 0,
            );

            $label = $canonical ?? $status;
            $options[$label] = $label;
        }

        return $options;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    public static function sendEmails($records)
    {
        $user = Auth::user();
        
        if (!$user) {
            Log::error("No authenticated user found!");
            return;
        }
    
        // Fetch updated user info from the database
        $user = \App\Models\User::find($user->id);
    
        // Get SMTP credentials (use system default if user's credentials are missing)
        $smtpUsername = $user->smtp_username ?? Config::get('mail.mailers.smtp.username');
        $smtpPassword = $user->smtp_password ?? Config::get('mail.mailers.smtp.password');
    
        // Ensure SMTP credentials are set correctly
        if (!$smtpUsername || !$smtpPassword) {
            Log::error("SMTP credentials missing for user: {$user->id}");
            return;
        }
    
        // Dynamically set the mail configuration
        Config::set('mail.mailers.smtp.username', $smtpUsername);
        Config::set('mail.mailers.smtp.password', $smtpPassword);
    
        // Convert a single record into a collection for uniform processing
        $records = is_array($records) || $records instanceof \Illuminate\Support\Collection ? $records : collect([$records]);
    
        foreach ($records as $record) {
            $draftMail = DraftMail::where('status', $record->status)->first();
            
            if (!$draftMail) {
                Log::error("No draft email found for status: {$record->status}");
                continue;
            }
    
            try {
                // Send the email
                Mail::to($record->email)->send(new CustomLeadEmail($record, $draftMail, $user));
    
                // Update the lead's status and last_contact_date
                $record->update([
                    'status' => $draftMail->new_status,
                    'last_contact_date' => now()->toDateString(),
                ]);
                $record->interactions()->create([
                    'lead_id' => $record->id,
                    'user_id' => Auth::id(),
                    'method' => 'Email',
                    'status' => $record->status,
                    'interaction_date' => Carbon::now(),
                ]);
    
                Log::info("Email successfully sent to: {$record->email}");
            } catch (\Exception $e) {
                Log::error("Email sending failed for {$record->email}: " . $e->getMessage());
            }
        }
    
        // Send a notification only if multiple emails were sent (bulk)
        if ($records->count() > 1) {
            Notification::make()
                ->title('Bulk Emails Sent')
                ->body('Emails have been sent to selected leads.')
                ->success()
                ->send();
        }
    }

    public static function getRelations(): array
    {
        return [
            InteractionsRelationManager::class,
        ];   
    }
}