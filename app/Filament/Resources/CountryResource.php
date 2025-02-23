<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountryResource\Pages;
use App\Filament\Resources\CountryResource\RelationManagers;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CountryResource\RelationManagers\CitiesRelationManager;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;
    protected static ?string $navigationGroup = 'Location Management';
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('iso')
                    ->required()
                    ->maxLength(2),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(80),
                Forms\Components\TextInput::make('nicename')
                    ->required()
                    ->maxLength(80),
                Forms\Components\TextInput::make('iso3')
                    ->maxLength(3),
                Forms\Components\TextInput::make('numcode')
                    ->numeric(),
                Forms\Components\TextInput::make('phonecode')
                    ->tel()
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('iso')->sortable(),
                Tables\Columns\TextColumn::make('name')->sortable(),
                Tables\Columns\TextColumn::make('nicename')->sortable(),
                Tables\Columns\TextColumn::make('iso3')->sortable(),
                Tables\Columns\TextColumn::make('numcode')->sortable(),
                Tables\Columns\TextColumn::make('phonecode')->sortable(),
                Tables\Columns\TextColumn::make('cities_count') // Show the number of cities
                    ->label('Cities')
                    ->counts('cities')
                    ->sortable(),
            ])
            ->filters([])
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
            CitiesRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCountries::route('/'),
            'create' => Pages\CreateCountry::route('/create'),
            'edit' => Pages\EditCountry::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System Management';
    }
}
