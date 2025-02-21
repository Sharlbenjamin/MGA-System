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
                    ->unique()
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