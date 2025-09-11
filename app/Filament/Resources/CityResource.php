<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationGroup = 'System';
protected static ?int $navigationSort = 7;
protected static ?string $navigationIcon = 'heroicon-o-map-pin'; // 📍 Cities Icon

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->searchable()->preload(),
                Forms\Components\Select::make('province_id')->relationship('province', 'name')->searchable()->preload(),
                Forms\Components\TextInput::make('name')->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('province.name')->label('province')->sortable()->searchable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->searchOnBlur()
            ->defaultSort('name');
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
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }

    public static function navigationItems(): array
{
    return [
        \Filament\Navigation\NavigationItem::make()
            ->label('Cities')
            ->url(self::getUrl('index'))
            ->icon('heroicon-o-location-marker')
            ->sort(4),
    ];
}
}
