<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Filament\Resources\ContactResource\RelationManagers;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use App\Models\Client;
use App\Models\Provider;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Country;
use App\Models\City;
use App\Models\ProviderBranch;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;


class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationGroup = 'Admin'; // ✅ Group under User Management
protected static ?int $navigationSort = 4; // ✅ Controls menu order
protected static ?string $navigationIcon = 'heroicon-o-phone'; // ✅ Adds a phone icon

    public static function form(Form $form): Form
{
    return $form->schema([
        Select::make('type')->label('Contact Type')
            ->options([
                'Client'   => 'Client',
                'Provider' => 'Provider',
                'Branch'   => 'Branch',
                'Patient'  => 'Patient',
            ])->reactive()->required(),
        Select::make('client_id')->label('Select Client')->options(Client::pluck('company_name', 'id'))->visible(fn ($get) => $get('type') === 'Client')->nullable(),
        Select::make('provider_id')->label('Select Provider')->options(Provider::pluck('name', 'id'))->visible(fn ($get) => $get('type') === 'Provider')->nullable(),
        Select::make('branch_id')->label('Select Branch')->options(ProviderBranch::pluck('branch_name', 'id'))->visible(fn ($get) => $get('type') === 'Branch')->nullable(),
        Select::make('patient_id')->label('Select Patient')->options(Patient::pluck('name', 'id'))->visible(fn ($get) => $get('type') === 'Patient')->nullable(),
        TextInput::make('name')->label('Contact Name')->required()->helperText('Dynamic names (Operation, Financial, GOP)'),
        TextInput::make('title')->label('Title')->nullable(),
        TextInput::make('email')->label('Email')->email()->unique('contacts', 'email', ignoreRecord: true)->nullable(),
        TextInput::make('second_email')->label('Second Email')->email()->nullable(),
        TextInput::make('phone_number')->label('Phone Number')->nullable(),
        TextInput::make('second_phone')->label('Second Phone')->nullable(),
        Select::make('country_id')->label('Country')->options(Country::pluck('name', 'id'))->reactive()->nullable(),
        Select::make('city_id')->label('City')->options(fn ($get) => City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive()->nullable(),
        Textarea::make('address')->label('Address')->nullable(),
        
        Select::make('preferred_contact')
            ->label('Preferred Contact Method')
            ->options([
                'Phone'        => 'Phone',
                'Second Phone' => 'Second Phone',
                'Email'        => 'Email',
                'Second Email' => 'Second Email',
                'first_whatsapp' => 'First WhatsApp',
                'second_whatsapp' => 'Second WhatsApp',
            ])
            ->nullable(),
        
        Select::make('status')
            ->label('Status')
            ->options([
                'Active'   => 'Active',
                'Inactive' => 'Inactive',
            ])
            ->default('Active')
            ->nullable(),
        
    ]);
}

public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->columns([
            TextColumn::make('type')->label('Type')->sortable(),
            TextColumn::make('name')->label('Name')->sortable()->searchable(),
            TextColumn::make('entity_name')->label('Entity Name')->sortable()->searchable(),
            TextColumn::make('title')->label('Title')->sortable()->searchable(),
            TextColumn::make('email')->label('Email')->sortable()->searchable(),
            TextColumn::make('phone_number')->label('Phone')->sortable(),
            TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
            TextColumn::make('city.name')->label('City')->sortable()->searchable(),
            BadgeColumn::make('preferred_contact')->label('Preferred Contact')
                ->colors([
                    'Phone'        => 'primary',
                    'Second Phone' => 'warning',
                    'Email'        => 'success',
                    'Second Email' => 'danger',
                ]),

            BadgeColumn::make('status')->label('Status')
                ->colors([
                    'Active'   => 'success',
                    'Inactive' => 'danger',
                ]),
        ])
        ->filters([
            SelectFilter::make('type')
                ->label('Contact Type')
                ->options([
                    'Client'   => 'Client',
                    'Provider' => 'Provider',
                    'Branch'   => 'Branch',
                    'Patient'  => 'Patient',
                ]),
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
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with('contactable');
}
}
