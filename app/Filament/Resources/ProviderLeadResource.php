<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderLeadResource\Pages;
use App\Filament\Resources\ProviderLeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\ProviderLead;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\Provider;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Mail;
use App\Models\DraftMail;
use Carbon\Carbon;
use App\Mail\CustomLeadEmail;
use App\Models\Interaction;
use Filament\Forms\ComponentContainer;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Config;

class ProviderLeadResource extends Resource
{
    protected static ?string $model = ProviderLead::class;

    protected static ?string $navigationGroup = 'PRM';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Provider Information')
                    ->schema([
                        Toggle::make('create_new_provider')
                            ->label('Create New Provider')
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('provider_id', null);
                                $set('new_provider_name', null);
                                $set('new_provider_type', null);
                                $set('new_provider_country_id', null);
                                $set('new_provider_status', null);
                            }),

                        // Existing Provider Selection
                        Select::make('provider_id')
                            ->label('Select Provider')
                            ->options(Provider::pluck('name', 'id'))
                            ->searchable()
                            ->reactive()
                            ->visible(fn (Get $get) => !$get('create_new_provider'))
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $provider = Provider::find($get('provider_id'));
                                if ($provider) {
                                    $set('city_id', null);
                                }
                            }),

                        // New Provider Creation Fields
                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_provider_name')
                                    ->label('Provider Name')
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->unique('providers', 'name', ignoreRecord: true),

                                Select::make('new_provider_type')
                                    ->label('Provider Type')
                                    ->options([
                                        'Doctor' => 'Doctor',
                                        'Hospital' => 'Hospital',
                                        'Clinic' => 'Clinic',
                                        'Dental' => 'Dental',
                                        'Agency' => 'Agency',
                                    ])
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Grid::make(2)
                            ->schema([
                                Select::make('new_provider_country_id')
                                    ->label('Country')
                                    ->options(Country::pluck('name', 'id'))
                                    ->searchable()
                                    ->reactive()
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('city_id', null);
                                    }),

                                Select::make('new_provider_status')
                                    ->label('Status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Hold' => 'Hold',
                                        'Potential' => 'Potential',
                                        'Black list' => 'Black List',
                                    ])
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_provider_email')
                                    ->label('Provider Email')
                                    ->email()
                                    ->unique('providers', 'email', ignoreRecord: true)
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $email = $get('new_provider_email');
                                        if ($email) {
                                            $exists = Provider::where('email', $email)->exists();
                                            if ($exists) {
                                                $set('new_provider_email', null);
                                                Notification::make()
                                                    ->title('Email Already Exists')
                                                    ->body('This email is already registered with another provider.')
                                                    ->danger()
                                                    ->send();
                                            }
                                        }
                                    }),

                                TextInput::make('new_provider_phone')
                                    ->label('Provider Phone')
                                    ->tel()
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_provider_payment_due')
                                    ->label('Payment Due (Days)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get) => $get('create_new_provider')),

                                Select::make('new_provider_payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'Online Link' => 'Online Link',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'AEAT' => 'AEAT',
                                    ])
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Textarea::make('new_provider_comment')
                            ->label('Provider Comment')
                            ->rows(3)
                            ->visible(fn (Get $get) => $get('create_new_provider')),
                    ])
                    ->collapsible(),

                Section::make('Provider Lead Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Lead Name')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('type')
                                    ->label('Lead Type')
                                    ->options([
                                        'Doctor' => 'Doctor',
                                        'Clinic' => 'Clinic',
                                        'Hospital' => 'Hospital',
                                        'Dental' => 'Dental',
                                    ])
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $email = $get('email');
                                        if ($email) {
                                            $exists = ProviderLead::where('email', $email)->exists();
                                            if ($exists) {
                                                $set('email', null);
                                                Notification::make()
                                                    ->title('Email Already Exists')
                                                    ->body('This email is already registered with another provider lead.')
                                                    ->danger()
                                                    ->send();
                                            }
                                        }
                                    }),

                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel(),
                            ]),

                        Select::make('city_id')
                            ->label('City')
                            ->options(function (Get $get) {
                                $providerId = $get('provider_id');
                                $countryId = $get('new_provider_country_id');
                                
                                if ($providerId) {
                                    $provider = Provider::find($providerId);
                                    return $provider ? City::where('country_id', $provider->country_id)->pluck('name', 'id') : [];
                                } elseif ($countryId) {
                                    return City::where('country_id', $countryId)->pluck('name', 'id');
                                }
                                
                                return [];
                            })
                            ->searchable()
                            ->reactive()
                            ->required(),

                        Select::make('service_types')
                            ->label('Service Types')
                            ->options(ServiceType::pluck('name', 'name'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->formatStateUsing(fn ($state) => is_string($state) ? explode(',', $state) : ($state ?? []))
                            ->dehydrateStateUsing(fn ($state) => is_array($state) ? implode(',', $state) : $state)
                            ->required(),

                        Select::make('communication_method')
                            ->label('Contact Method')
                            ->options([
                                'Email' => 'Email',
                                'WhatsApp' => 'WhatsApp',
                                'Phone' => 'Phone',
                            ])
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'Pending information' => 'Pending Information',
                                'Step one' => 'Step One',
                                'Step one sent' => 'Step One Sent',
                                'Reminder' => 'Reminder',
                                'Reminder sent' => 'Reminder Sent',
                                'Discount' => 'Discount',
                                'Discount sent' => 'Discount Sent',
                                'Step two' => 'Step Two',
                                'Step two sent' => 'Step Two Sent',
                                'Presentation' => 'Presentation',
                                'Presentation sent' => 'Presentation Sent',
                                'Contract' => 'Contract',
                                'Contract sent' => 'Contract Sent',
                                'Fake Case' => 'Fake Case',
                                'Fake Case sent' => 'Fake Case sent',
                                'Cancel Case' => 'Cancel Case',
                                'Cancel Case sent' => 'Cancel Case sent',
                            ])
                            ->required(),

                        DatePicker::make('last_contact_date')
                            ->label('Last Contact Date'),

                        Textarea::make('comment')
                            ->label('Comment')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        $ActionStatuses = ['Pending information', 'Step one', 'Reminder', 'Discount', 'Step two', 'Presentation', 'Contract', 'Fake Case', 'Cancel Case'];
        return $table
        ->query(
            ProviderLead::query()->whereHas('provider', function ($query) {
                $query->where('status', 'Potential');
            })
        )
            ->columns([
                TextColumn::make('name')->label('Lead Name')->sortable()->searchable(),
                TextColumn::make('email')->label('Lead Email')->sortable()->searchable(),
                TextColumn::make('provider.name')->label('Provider')->sortable()->searchable(),
                TextColumn::make('city.name')->label('City')->sortable()->searchable(),
                TextColumn::make('service_types')
                    ->label('Service Types')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_string($state) ? $state : implode(', ', (array) $state)),

                TextColumn::make('communication_method')->label('Contact Method')->sortable(),

                TextColumn::make('status')
                    ->colors([
                        'gray' => 'Pending information',
                        'blue' => 'Step one',
                        'blue' => 'Fake Case',
                        'blue' => 'Fake Case Sent',
                        'green' => 'Step one sent',
                        'yellow' => 'Reminder',
                        'red' => 'Reminder sent',
                        'purple' => 'Discount',
                        'pink' => 'Discount sent',
                        'cyan' => 'Step two',
                        'orange' => 'Step two sent',
                        'lime' => 'Presentation',
                        'amber' => 'Presentation sent',
                        'teal' => 'Contract',
                        'emerald' => 'Contract sent',
                    ])
                    ->sortable(),

                TextColumn::make('last_contact_date')->label('Last Contact')->date('d-m-Y')->sortable(),

            ])->actions([
                Action::make('sendEmail')->label('Email')->icon('heroicon-o-paper-airplane')->requiresConfirmation()->color('success')
                    ->action(fn (ProviderLead $record) => static::processProviderEmails(collect([$record]))),
            ])
            ->bulkActions([
                BulkAction::make('sendEmailBulk')
                    ->label('Send Bulk Emails')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(fn (SupportCollection $records) => static::processProviderEmails($records)),

                Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('send_tailored_mail')->label('Send Tailored Mail')
                    ->form([
                        TextInput::make('subject')->label('Email Subject')->required(),
                        Textarea::make('body')->label('Email Body')->required(),
                    ])
                    ->action(function (ComponentContainer $form, $records) {
                        $subject = $form->getState()['subject'];
                        $body = $form->getState()['body'];

                        foreach ($records as $lead) {
                            ProviderLead::sendTailoredMail([$lead->email], $subject, $body);
                        }

                    })
            ])
            ->filters([
                SelectFilter::make('status')->multiple()
                        ->options([
                            'Pending information' => 'Pending information',
                            'Step one' => 'Step one',
                            'Step one sent' => 'Step one sent',
                            'Reminder' => 'Reminder',
                            'Reminder sent' => 'Reminder sent',
                            'Discount' => 'Discount',
                            'Discount sent' => 'Discount sent',
                            'Step two' => 'Step two',
                            'Step two sent' => 'Step two sent',
                            'Presentation' => 'Presentation',
                            'Presentation sent' => 'Presentation sent',
                            'Contract' => 'Contract',
                            'Contract sent' => 'Contract sent',
                            'Fake Case' => 'Fake Case',
                            'Fake Case sent' => 'Fake Case sent',
                            'Cancel Case' => 'Cancel Case',
                            'Cancel Case sent' => 'Cancel Case sent',
                        ])->label('Filter by Status')->attribute('status'),
                Filter::make('needs_action')->label('Needs Action')->query(fn ($query, $data) => $data ? $query->whereIn('status', $ActionStatuses) : $query),
                SelectFilter::make('city_id')
                ->label('Filter by City')
                ->options(City::whereHas('country', fn ($q) => $q->whereIn('id', [179, 73, 201, 119, 94, 156]))->pluck('name', 'id'))
                ->preload()
                ->multiple()
            ]);
    }

    public static function processProviderEmails(SupportCollection $providerLeads)
    {
        // Convert to Eloquent Collection (Fetch actual models)
        $providerLeads = ProviderLead::whereIn('id', $providerLeads->pluck('id'))->get();

        // Get authenticated user
        $user = Auth::user();

        // System default SMTP credentials
        $systemSmtpUsername = $user->smtp_username ?? Config::get('mail.mailers.smtp.username');
        $systemSmtpPassword = $user->smtp_password ?? Config::get('mail.mailers.smtp.password');

        foreach ($providerLeads as $providerLead) {
            // Find the corresponding draft mail based on provider lead status
            $draftMail = DraftMail::where('type', 'Provider')
                ->where('status', $providerLead->status)
                ->first();

            if (!$draftMail) {
                Notification::make()->title('Email Not Sent')->body("Draft Mail for step '{$providerLead->status}' not found.")->danger()->send();
                continue; // Skip if no matching draft email is found
            }

            // Use user SMTP credentials if available, otherwise use system defaults
            $smtpUser = $user && $user->smtp_username ? $user->smtp_username : $systemSmtpUsername;
            $smtpPassword = $user && $user->smtp_password ? $user->smtp_password : $systemSmtpPassword;

            // Set SMTP dynamically for each email
            Config::set('mail.mailers.smtp.username', $smtpUser);
            Config::set('mail.mailers.smtp.password', $smtpPassword);

            // Send email using CustomLeadEmail Mailable
            try {
                Mail::to($providerLead->email)->send(new CustomLeadEmail($providerLead, $draftMail, $user));

                // Update provider lead's status and last_contact_date
                $providerLead->update([
                    'status' => $draftMail->new_status,
                    'last_contact_date' => Carbon::now(),
                ]);

                $providerLead->interactions()->create([
                    'user_id' => Auth::id(),
                    'provider_lead_id' => $providerLead->id,
                    'method' => 'Email',
                    'status' => $providerLead->status,
                    'interaction_date' => Carbon::now(),
                ]);
            } catch (\Exception $e) {
                Notification::make()->title('Email Sending Failed')->body("Email sending failed for {$providerLead->email}: " . $e->getMessage())->danger()->send();
                Log::error("Email sending failed for {$providerLead->email}: " . $e->getMessage());
            }
        }
    }

    public static function getRelations(): array
    {
        return [
            InteractionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderLeads::route('/'),
            'create' => Pages\CreateProviderLead::route('/create'),
            'edit' => Pages\EditProviderLead::route('/{record}/edit'),
        ];
    }
}