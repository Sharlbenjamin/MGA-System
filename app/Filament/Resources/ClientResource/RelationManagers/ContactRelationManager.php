<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\City;
use App\Models\Client;
use App\Models\Country;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class ContactRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('name')->label('Contact Name')->required()->helperText('Enter the contact name (e.g., Operation, Financial, GOP)'),
            TextInput::make('title')->label('Title')->nullable(),
            TextInput::make('email')->label('Email')->email()->unique('contacts', 'email', ignoreRecord: true)->nullable(),
            TextInput::make('second_email')->label('Second Email')->email()->nullable(),
            TextInput::make('phone_number')->label('Phone Number')->tel()->prefix('+')->mask('999999999999999')->placeholder('34612345678')->helperText('Enter country code + number without spaces (e.g., 34612345678)')->maxLength(15)->minLength(10)->nullable(),
            TextInput::make('second_phone')->label('Second Phone')->tel()->prefix('+')->mask('999999999999999')->placeholder('34612345678')->helperText('Enter country code + number without spaces (e.g., 34612345678)')->maxLength(15)->minLength(10)->nullable(),
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
                    'first_whatsapp' => 'First Whatsapp',
                    'second_whatsapp' => 'Second Whatsapp',
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


    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('phone_number')->label('Phone')->sortable()->searchable(),
                TextColumn::make('address')->label('Address')->sortable()->searchable()->limit(50),
            ])
            ->filters([
                // Add any filters here if needed
            ]) ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}