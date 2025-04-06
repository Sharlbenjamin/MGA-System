<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileFeeResource\Pages;
use App\Models\FileFee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;

class FileFeeResource extends Resource
{
    protected static ?string $model = FileFee::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_type_id')->relationship('serviceType', 'name')->nullable(),
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->nullable(),
                Forms\Components\Select::make('city_id')->relationship('city', 'name')->nullable(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serviceType.name'),
                Tables\Columns\TextColumn::make('country.name'),
                Tables\Columns\TextColumn::make('city.name'),
                Tables\Columns\TextColumn::make('amount')
                    ->money(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListFileFees::route('/'),
            'create' => Pages\CreateFileFee::route('/create'),
            'edit' => Pages\EditFileFee::route('/{record}/edit'),
        ];
    }
}