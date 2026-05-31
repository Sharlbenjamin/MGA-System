<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Models\City;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                Forms\Components\Select::make('country_id')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Forms\Components\Select::make('province_id')
                    ->relationship('province', 'name')
                    ->searchable()
                    ->preload()
                    ->live(),
                Forms\Components\Select::make('name')
                    ->label('City Name')
                    ->required()
                    ->searchable()
                    ->preload(false)
                    ->disabled(fn (Get $get): bool => ! $get('country_id'))
                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                        if (strlen($search) < 1) {
                            return [];
                        }

                        $countryId = $get('country_id');
                        if (! $countryId) {
                            return [];
                        }

                        $provinceId = $get('province_id');
                        $results = [];

                        foreach (City::findSimilar($search, $countryId, $provinceId) as $city) {
                            $label = $city->name;

                            if ($city->province) {
                                $label .= " ({$city->province->name})";
                            }

                            $results[$city->name] = $label;
                        }

                        $duplicate = City::findDuplicate($search, $countryId, $provinceId);

                        if ($duplicate) {
                            $results[$duplicate->name] = "Already exists: {$duplicate->name}";
                        } elseif (! array_key_exists($search, $results)) {
                            $results[$search] = "Create \"{$search}\"";
                        }

                        return $results;
                    })
                    ->getOptionLabelUsing(fn (?string $value): ?string => $value)
                    ->helperText(fn (Get $get): string => $get('country_id')
                        ? 'Search existing cities or type a new name. Duplicates are checked ignoring case and accents.'
                        : 'Select a country first, then search or enter the city name.')
                    ->rules([
                        fn (Get $get, Forms\Components\Component $component): Closure => function (string $attribute, $value, Closure $fail) use ($get, $component) {
                            if (blank($value)) {
                                return;
                            }

                            $record = $component->getLivewire()->record ?? null;

                            $duplicate = City::findDuplicate(
                                $value,
                                $get('country_id'),
                                $get('province_id'),
                                $record?->id,
                            );

                            if ($duplicate) {
                                $fail("A city matching \"{$duplicate->name}\" already exists. Check spelling, accents, or apostrophes.");
                            }
                        },
                    ]),
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
            RelationManagers\FileRelationManager::class,
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
