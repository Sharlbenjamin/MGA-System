<?php
namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\Lead;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use App\Mail\CustomLeadEmail;
use App\Models\Client;
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
                Select::make('client_id')->relationship('client', 'company_name')->required()->preload()->searchable(),
                Select::make('status')->options($leadStatuses)->required()->preload()->searchable(),
                TextInput::make('first_name')->required(),
                TextInput::make('email')->email()->unique('leads', 'email', ignoreRecord: true)->required(),
                TextInput::make('phone')->label('Phone')->nullable(),
                TextInput::make('linked_in')->label('Linked In')->nullable(),
                Select::make('contact_method')->options($methods)->preload()->searchable(),
                DatePicker::make('last_contact_date')->nullable(),
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
    
        // Get SMTP credentials (use system default if user’s credentials are missing)
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