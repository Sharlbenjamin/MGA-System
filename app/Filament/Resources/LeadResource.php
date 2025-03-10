<?php
namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
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
use Illuminate\Support\Facades\Mail;
use App\Models\DraftMail;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Filters\SelectFilter;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb'; // 💡 Leads Icon

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('client_id')
                    ->relationship('client', 'company_name')
                    ->required(),

                TextInput::make('email')
                    ->email()
                    ->unique('leads', 'email', ignoreRecord: true)
                    ->required(),

                TextInput::make('first_name')
                    ->required(),

                Select::make('status')
                    ->options([
                        'Introduction' => 'Introduction',
                        'Introduction Sent' => 'Introduction Sent',
                        'Reminder' => 'Reminder',
                        'Reminder Sent' => 'Reminder Sent',
                        'Presentation' => 'Presentation',
                        'Presentation Sent' => 'Presentation Sent',
                        'Price List' => 'Price List',
                        'Price List Sent' => 'Price List Sent',
                        'Contract' => 'Contract',
                        'Contract Sent' => 'Contract Sent',
                        'Interested' => 'Interested',
                        'Error' => 'Error',
                        'Partner' => 'Partner',
                        'Rejected' => 'Rejected',
                    ])
                    ->required(),

                DatePicker::make('last_contact_date')
                    ->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('client.company_name')->sortable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('first_name')->sortable()->searchable(),
                TextColumn::make('status')->badge()->sortable()->searchable(),
                TextColumn::make('last_contact_date')->date(),
            ])
            ->actions([
                Action::make('Send Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(fn ($record) => self::sendEmails($record))
                    ->color('success'),
            ]) ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Introduction' => 'Introduction',
                        'Introduction Sent' => 'Introduction Sent',
                        'Reminder' => 'Reminder',
                        'Reminder Sent' => 'Reminder Sent',
                        'Presentation' => 'Presentation',
                        'Presentation Sent' => 'Presentation Sent',
                        'Price List' => 'Price List',
                        'Price List Sent' => 'Price List Sent',
                        'Contract' => 'Contract',
                        'Contract Sent' => 'Contract Sent',
                        'Interested' => 'Interested',
                        'Error' => 'Error',
                        'Partner' => 'Partner',
                        'Rejected' => 'Rejected',
                    ])
                    ->label('Filter by Status')
                    ->attribute('status'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('Send Bulk Emails')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->action(fn ($records) => self::sendEmails($records))
                        ->deselectRecordsAfterCompletion()
                        ->color('success'),
                ]),
            ]);;
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
    

    public static function navigationItems(): array
{
    return [
        \Filament\Navigation\NavigationItem::make()
            ->label('Leads')
            ->url(self::getUrl('index'))
            ->icon('heroicon-o-light-bulb')
            ->sort(2),
    ];
}

}