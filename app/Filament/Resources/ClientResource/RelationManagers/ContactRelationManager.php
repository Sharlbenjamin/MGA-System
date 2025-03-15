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
            TextInput::make('name')->label('Contact Name')->required(),
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