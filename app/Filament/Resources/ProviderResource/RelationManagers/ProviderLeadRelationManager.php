<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\City;
use App\Models\Provider;
use App\Models\ServiceType;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProviderLeadRelationManager extends RelationManager
{
    protected static string $relationship = 'Leads';

    public function form(Form $form): Form
    {
        return $form
        ->schema([
            Select::make('provider_id')->label('Provider')->options(Provider::pluck('name', 'id'))->searchable()->reactive()->required(),

            Select::make('city_id')->label('City')
                ->options(fn ($get) => 
                    City::where('country_id', Provider::where('id', $get('provider_id'))->value('country_id'))->pluck('name', 'id')
                )->searchable()->reactive()->required(),
            Select::make('service_types')
                ->label('Service Types')
                ->options(ServiceType::pluck('name', 'name')) // ✅ Fetch service type names
                ->multiple()->preload()->searchable()
                ->formatStateUsing(fn ($state) => is_string($state) ? explode(',', $state) : ($state ?? [])) // ✅ Convert string to array before display
                ->dehydrateStateUsing(fn ($state) => is_array($state) ? implode(',', $state) : $state) // ✅ Convert array back to string on save
                ->required(),
            TextInput::make('name')->label('Lead Name')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->nullable(),
            TextInput::make('phone')->label('Phone')->tel()->nullable(),
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
            DatePicker::make('last_contact_date')->label('Last Contact Date')->date('d-m-Y')->nullable(),
            Textarea::make('comment')->label('Comment')->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Leads')
            ->columns([
            TextColumn::make('name')->label('Lead Name')->sortable()->searchable(),
            TextColumn::make('provider.name')->label('Provider')->sortable()->searchable(),
            TextColumn::make('city.name')->label('City')->sortable()->searchable(),
            TextColumn::make('service_types')
                ->label('Service Types')
                ->badge()
                ->formatStateUsing(fn ($state) => is_string($state) ? $state : implode(', ', (array) $state)), // ✅ Convert array to string

            TextColumn::make('communication_method')->label('Contact Method')->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
