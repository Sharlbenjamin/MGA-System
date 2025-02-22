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

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

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
                TextColumn::make('status')->badge(),
                TextColumn::make('last_contact_date')->date(),
            ])
            ->actions([
                Action::make('Send Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $user = Auth::user();
            
                        if (!$user) {
                            Log::error("No authenticated user found!");
                            return;
                        }
            
                        // Fetch updated user info from the database
                        $user = \App\Models\User::find($user->id);
            
                        Log::info("Using SMTP Credentials Before Sending Email:", [
                            'email' => $user->email,
                            'smtp_username' => $user->smtp_username,
                            'smtp_password' => $user->smtp_password,
                        ]);
            
                        // Dynamically set the mail configuration
                        Config::set('mail.mailers.smtp.username', $user->smtp_username);
                        Config::set('mail.mailers.smtp.password', $user->smtp_password);
            
                        // Confirm settings were updated
                        Log::info("Current Mail Config:", [
                            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
                            'MAIL_PASSWORD' => config('mail.mailers.smtp.password'),
                        ]);
            
                        // Fetch the corresponding draft email
                        $draftMail = DraftMail::where('status', $record->status)->first();
                        if (!$draftMail) {
                            Log::error("No draft email found for status: {$record->status}");
                            return;
                        }
            
                        // Log the subject before sending
                        Log::info("Email Subject Before Sending: " . $draftMail->mail_name);
            
                        // Test sending a different email class
                        Mail::to($record->email)->send(new CustomLeadEmail($record, $draftMail, $user));
            
                        // Update the lead's status and last_contact_date
                        $record->update([
                            'status' => $draftMail->new_status,
                            'last_contact_date' => now()->toDateString(), // Set last contact date to today
                        ]);
            
                        Log::info("Email successfully sent to: {$record->email}");
                        Log::info("Lead status updated to: {$draftMail->new_status}");
                        Log::info("Last contact date updated to: " . now()->toDateString());
                    })
                    ->color('success'),
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
}