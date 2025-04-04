<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\City;
use App\Models\Provider;
use App\Models\Province;
use App\Models\ServiceType;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProviderBranchRelationManager extends RelationManager
{
    protected static string $relationship = 'Branches';

    public static function query(Builder $query): Builder
    {
        return $query->where('provider_id', static::getOwnerRecord()->id);
    }
    public function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            TextInput::make('branch_name')->label('Branch Name')->required()->maxLength(255),
            Select::make('city_id')->label('City')->options(fn ($get) => City::where('country_id', Provider::where('id',$this->getOwnerRecord()->id)->value('country_id'))->pluck('name', 'id'))->searchable()->reactive()->required(),
            Select::make('province')->label('Province')->options(fn ($get) => Province::where('country_id', Provider::where('id', $this->getOwnerRecord()->id)->value('country_id'))->pluck('name', 'id'))->searchable()->reactive(),
            Select::make('status')->label('Status')->options(['Active' => 'Active','Hold' => 'Hold',])->required(),
            Select::make('priority')->label('Priority')->options([
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
                    '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
                ])->required(),

            Select::make('service_types')
                ->label('Service Types')
                ->multiple()
                ->options(ServiceType::pluck('name', 'name'))
                ->searchable()
                ->required(),
            //Select::make('communication_method')->label('Communication Method')->options(['Email' => 'Email', 'WhatsApp' => 'WhatsApp', 'Phone' => 'Phone'])->required(),

            TextInput::make('day_cost')->label('Day Cost')->numeric()->nullable(),
            TextInput::make('night_cost')->label('Night Cost')->numeric()->nullable(),
            TextInput::make('weekend_cost')->label('Weekend Cost')->numeric()->nullable(),
            TextInput::make('weekend_night_cost')->label('Weekend Night Cost')->numeric()->nullable(),

            Section::make('Medical Services')
                ->schema([
                    Toggle::make('emergency')->label('Emergency')->inline(),
                    Toggle::make('pediatrician_emergency')->label('Pediatrician Emergency')->inline(),
                    Toggle::make('dental')->label('Dental')->inline(),
                    Toggle::make('pediatrician')->label('Pediatrician')->inline(),
                    Toggle::make('gynecology')->label('Gynecology')->inline(),
                    Toggle::make('urology')->label('Urology')->inline(),
                    Toggle::make('cardiology')->label('Cardiology')->inline(),
                    Toggle::make('ophthalmology')->label('Ophthalmology')->inline(),
                    Toggle::make('trauma_orthopedics')->label('Trauma / Orthopedics')->inline(),
                    Toggle::make('surgery')->label('Surgery')->inline(),
                    Toggle::make('intensive_care')->label('Intensive Care')->inline(),
                    Toggle::make('obstetrics_delivery')->label('Obstetrics / Delivery')->inline(),
                    Toggle::make('hyperbaric_chamber')->label('Hyperbaric Chamber')->inline(),
                ])


        ]);
}

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Branches')
            ->columns([
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
