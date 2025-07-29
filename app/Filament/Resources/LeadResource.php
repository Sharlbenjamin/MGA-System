<?php
namespace App\Filament\Resources;

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
use Illuminate\Database\Eloquent\Collection;

class LeadResource extends Resource
{
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
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('client_id', null);
                                $set('new_client_company_name', null);
                                $set('new_client_type', null);
                                $set('new_client_status', null);
                                $set('new_client_initials', null);
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
                                    ->unique('clients', 'company_name', ignoreRecord: true),

                                Select::make('new_client_type')
                                    ->label('Client Type')
                                    ->options([
                                        'Assistance' => 'Assistance',
                                        'Insurance' => 'Insurance',
                                        'Agency' => 'Agency',
                                    ])
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
                                    ->required(fn (Get $get) => $get('create_new_client'))
                                    ->visible(fn (Get $get) => $get('create_new_client')),

                                TextInput::make('new_client_initials')
                                    ->label('Initials')
                                    ->maxLength(10)
                                    ->required(fn (Get $get) => $get('create_new_client'))
                                    ->visible(fn (Get $get) => $get('create_new_client')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_client')),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_client_email')
                                    ->label('Client Email')
                                    ->email()
                                    ->unique('clients', 'email', ignoreRecord: true)
                                    ->visible(fn (Get $get) => $get('create_new_client'))
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $email = $get('new_client_email');
                                        if ($email) {
                                            $exists = Client::where('email', $email)->exists();
                                            if ($exists) {
                                                $set('new_client_email', null);
                                                Notification::make()
                                                    ->title('Email Already Exists')
                                                    ->body('This email is already registered with another client.')
                                                    ->danger()
                                                    ->send();
                                            }
                                        }
                                    }),

                                TextInput::make('new_client_phone')
                                    ->label('Client Phone')
                                    ->tel()
                                    ->visible(fn (Get $get) => $get('create_new_client')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_client')),

                        TextInput::make('new_client_number_requests')
                            ->label('Number of Requests')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
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
        ->query(Lead::query()->whereHas('client', function ($query) {$query->whereNotIn('status', ['Active', 'On Hold', 'Rejected']);}))
            ->columns([
                TextColumn::make('client.company_name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('first_name')->sortable()->searchable(),
                TextColumn::make('contact_method')->sortable()->searchable(),
                TextColumn::make('status')->badge()->sortable()->color(fn (string $state): string => match ($state) {
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
                TextColumn::make('last_contact_date')->date()->sortable()->searchable(),
            ])
            ->actions([
                Action::make('Send Email')->icon('heroicon-o-paper-airplane')->requiresConfirmation()->action(fn ($record) => self::sendEmails($record))->color('success'),
            ]) ->filters([
                SelectFilter::make('client_id')->label('Client Status')->options(Client::query()->distinct()->orderBy('status')->pluck('status', 'id')->unique()->toArray())->searchable()->preload()->multiple(),
                Filter::make('needs_action')->label('Needs Action')->query(fn ($query, $data) => $data ? $query->whereIn('status', $ActionStatuses) : $query),
                SelectFilter::make('status')->multiple()->options($leadStatuses)->label('Filter by Status')->attribute('status'),
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