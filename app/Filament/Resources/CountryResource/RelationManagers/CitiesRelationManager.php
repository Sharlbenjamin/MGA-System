<?php

namespace App\Filament\Resources\CountryResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Resources\RelationManagers\Concerns\ManagesRelations;

class CitiesRelationManager extends RelationManager
{
    //use ManagesRelations;

    protected static string $relationship = 'cities';
    protected static ?string $title = 'Cities';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->searchable()->preload(),
                Forms\Components\Select::make('province_id')->relationship('province', 'name')->searchable()->preload(),
                Forms\Components\TextInput::make('name')->required(),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable(),
            Tables\Columns\TextColumn::make('province.name')->label('province')->sortable(),
        ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}