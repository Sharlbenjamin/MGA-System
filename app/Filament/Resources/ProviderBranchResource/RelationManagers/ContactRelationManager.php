<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

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
            Select::make('name')->multiple()->label('Contact Name')->required()->helperText('Dynamic names (Operation, Financial, GOP, Appointment)')->options([
                'Operation' => 'Operation',
                'Financial' => 'Financial',
                'GOP' => 'GOP',
                'Appointment' => 'Appointment',
            ]),
            TextInput::make('title')->label('Name')->nullable(),
            TextInput::make('email')->label('Email')->email()->unique('contacts', 'email', ignoreRecord: true)->nullable(),
            TextInput::make('second_email')->label('Second Email')->email()->nullable(),
            TextInput::make('phone_number')->label('Phone Number')->tel()->prefix('+')->mask('999999999999999')->placeholder('34612345678')->helperText('Enter country code + number without spaces (e.g., 34612345678)')->maxLength(15)->minLength(10)->nullable(),
            TextInput::make('second_phone')->label('Second Phone')->tel()->prefix('+')->mask('999999999999999')->placeholder('34612345678')->helperText('Enter country code + number without spaces (e.g., 34612345678)')->maxLength(15)->minLength(10)->nullable(),
            Select::make('country_id')->label('Country')->options(Country::pluck('name', 'id'))->reactive()->nullable()->searchable(),
            Select::make('city_id')->label('City')->options(fn ($get) => City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive()->nullable()->searchable(),
            Textarea::make('address')->label('Address')->nullable(),

            Select::make('preferred_contact')
                ->label('Preferred Contact Method')
                ->options([
                    'Phone'        => 'Phone',
                    'Second Phone' => 'Second Phone',
                    'Email'        => 'Email',
                    'Second Email' => 'Second Email',
                    'First Whatsapp' => 'First Whatsapp',
                    'Second Whatsapp' => 'Second Whatsapp',
                    'First SMS' => 'First SMS',
                    'Second SMS' => 'Second SMS',
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
                TextColumn::make('phone')->sortable()->searchable(),
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