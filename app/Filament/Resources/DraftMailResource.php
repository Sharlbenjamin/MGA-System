<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DraftMailResource\Pages;
use App\Filament\Resources\DraftMailResource\RelationManagers;
use App\Models\DraftMail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Navigation\NavigationItem;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
class DraftMailResource extends Resource
{
    protected static ?string $model = DraftMail::class;

    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-envelope'; // ✉️ Draft Mails Icon

    /**
     * Get all available statuses for a given type (including custom ones)
     */
    public static function getAvailableStatuses($type)
    {
        $defaultStatuses = [];
        
        if ($type === 'Provider') {
            $defaultStatuses = [
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
            ];
        } elseif ($type === 'File') {
            $defaultStatuses = [
                'New' => 'New',
                'Handling' => 'Handling',
                'Available' => 'Available',
                'Confirmed' => 'Confirmed',
                'Requesting GOP' => 'Requesting GOP',
                'Assisted' => 'Assisted',
                'Hold' => 'Hold',
                'Void' => 'Void',
            ];
        } else { // Client
            $defaultStatuses = [
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
            ];
        }

        // Get all unique statuses from existing draft mails of this type
        $existingStatuses = DraftMail::where('type', $type)
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->toArray();

        $existingNewStatuses = DraftMail::where('type', $type)
            ->whereNotNull('new_status')
            ->distinct()
            ->pluck('new_status')
            ->toArray();

        // Convert to associative array
        $customStatuses = array_combine($existingStatuses, $existingStatuses);
        $customNewStatuses = array_combine($existingNewStatuses, $existingNewStatuses);

        // Merge all statuses
        return array_merge($defaultStatuses, $customStatuses, $customNewStatuses);
    }

public static function form(Form $form): Form
{
    $leadStatuses = [
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
    ];

    $providerLeadStatuses = [
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
    ];

    $fileStatuses = [
        'New' => 'New',
        'Handling' => 'Handling',
        'Available' => 'Available',
        'Confirmed' => 'Confirmed',
        'Requesting GOP' => 'Requesting GOP',
        'Assisted' => 'Assisted',
        'Hold' => 'Hold',
        'Void' => 'Void',
    ];
     // $notificationStatuses = [
    //     'Client New File' => 'Client New File',
    //     'Client Handling File' => 'Client Handling File',
    //     'Client File Available' => 'Client File Available',
    //     'Client File Assisted' => 'Client File Assisted',
    //     'Client File On Hold' => 'Client File On Hold',
    //     'Client File Cancelled' => 'Client File Cancelled',
    //     'Patient New File' => 'Patient New File',
    //     'Patient Appointment Confirmed' => 'Patient Appointment Confirmed',
    //     'Patient Appointment Avilable' => 'Patient Appointment Avilable',
    //     'Patient Appointment Assisted' => 'Patient Appointment Assisted',
    //     'Branch Requesting New Appointment' => 'Branch Requesting New Appointment',
    //     'Branch Appointment Update' => 'Branch Appointment Update',
    //     'Branch Appointment Confirmed' => 'Branch Appointment Confirmed',
    //     'Branch Appointment Cancelled' => 'Branch Appointment Cancelled',
    //     'Our Appointment Booking Instructions' => 'Appointment Booking Instructions',
    //     'Our Confirm Appointment Instructions' => 'Confirm Appointment Instructions',
    //     'Cancel Appointment Instructions' => 'Cancel Appointment Instructions',
    // ];


    return $form
        ->schema([
            TextInput::make('mail_name')->required(),
            Textarea::make('body_mail')->required()->columnSpanFull()
                ->helperText('Use {name}, {email}, {serice}, {city}, {username}, and {company} as placeholders for lead data.'),

            Select::make('type')->label('Type')
                ->options([
                    'Provider' => 'Provider',
                    'Client' => 'Client',
                    'File' => 'File',
                ])->reactive()->required(),

            TextInput::make('status')
                ->label('Lead Status')
                ->reactive()
                ->datalist(function ($get) {
                    $type = $get('type');
                    if (!$type) return [];
                    $statuses = self::getAvailableStatuses($type);
                    return array_keys($statuses);
                })
                ->helperText('Type a custom status or select from the dropdown'),

            TextInput::make('new_status')
                ->label('New Status After Sending')
                ->reactive()
                ->datalist(function ($get) {
                    $type = $get('type');
                    if (!$type) return [];
                    $statuses = self::getAvailableStatuses($type);
                    return array_keys($statuses);
                })
                ->helperText('Type a custom status or select from the dropdown'),
        ]);
}

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mail_name'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('new_status'),
                Tables\Columns\TextColumn::make('type'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'Provider' => 'Provider',
                        'Client' => 'Client',
                        'File' => 'File',
                    ])
                    ->label('Filter by Type'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDraftMails::route('/'),
            'create' => Pages\CreateDraftMail::route('/create'),
            'edit' => Pages\EditDraftMail::route('/{record}/edit'),
        ];
    }

    public static function navigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make()
                ->label('Draft Mails')
                ->url(self::getUrl('index'))
                ->icon('heroicon-o-mail')
                ->sort(5),
        ];
    }
}
