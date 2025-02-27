<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProviderLeadResource\Pages;
use App\Models\ProviderLead;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Config;

class ProviderLeadResource extends Resource
{
    protected static ?string $model = ProviderLead::class;

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            Select::make('provider_id')
                ->label('Provider')
                ->options(Provider::pluck('name', 'id'))
                ->searchable()
                ->reactive()
                ->required(),

            Select::make('city_id')
                ->label('City')
                ->options(fn ($get) => 
                    City::where('country_id', Provider::where('id', $get('provider_id'))->value('country_id'))->pluck('name', 'id')
                )
                ->searchable()
                ->reactive()
                ->required(),

            Select::make('service_types')
                ->label('Service Types')
                ->options(ServiceType::pluck('name', 'name')) // ✅ Fetch service type names
                ->multiple() // ✅ Allow multiple selections
                ->preload()
                ->searchable()
                ->formatStateUsing(fn ($state) => is_string($state) ? explode(',', $state) : ($state ?? [])) // ✅ Convert string to array before display
                ->dehydrateStateUsing(fn ($state) => is_array($state) ? implode(',', $state) : $state) // ✅ Convert array back to string on save
                ->required(),

            TextInput::make('name')
                ->label('Lead Name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->nullable(),

            TextInput::make('phone')
                ->label('Phone')
                ->tel()
                ->nullable(),

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
                ])
                ->required(),

            DatePicker::make('last_contact_date')
                ->label('Last Contact Date')
                ->nullable(),

            Textarea::make('comment')
                ->label('Comment')
                ->nullable(),
        ]);
}

public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->columns([
            TextColumn::make('name')->label('Lead Name')->sortable()->searchable(),

            TextColumn::make('provider.name')->label('Provider')->sortable()->searchable(),

            TextColumn::make('city.name')->label('City')->sortable()->searchable(),

            TextColumn::make('service_types')
                ->label('Service Types')
                ->badge()
                ->formatStateUsing(fn ($state) => is_string($state) ? $state : implode(', ', (array) $state)), // ✅ Convert array to string

            TextColumn::make('communication_method')->label('Contact Method')->sortable(),

            TextColumn::make('status')
                ->colors([
                    'gray' => 'Pending information',
                    'blue' => 'Step one',
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

            TextColumn::make('last_contact_date')->label('Last Contact')->sortable(),

        ])->actions([
            Action::make('sendEmail')
                ->label('Email')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->color('success')
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
        ])
        ->filters([
            // Add filters if needed
        ]);
}


public static function processProviderEmails(SupportCollection $providerLeads)
{
    // Convert to Eloquent Collection (Fetch actual models)
    $providerLeads = ProviderLead::whereIn('id', $providerLeads->pluck('id'))->get();

    // Get authenticated user
    $user = Auth::user();

    // System default SMTP credentials
    $systemSmtpUsername = Config::get('mail.mailers.smtp.username', 'default-smtp@example.com');
    $systemSmtpPassword = Config::get('mail.mailers.smtp.password', 'default-smtp-password');

    foreach ($providerLeads as $providerLead) {
        // Find the corresponding draft mail based on provider lead status
        $draftMail = DraftMail::where('type', 'Provider')
            ->where('status', $providerLead->status)
            ->first();

        if (!$draftMail) {
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
        } catch (\Exception $e) {
            Log::error("Email sending failed for {$providerLead->email}: " . $e->getMessage());
        }
    }
}
    public static function getRelations(): array
    {
        return [
            // Define any related models if needed
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