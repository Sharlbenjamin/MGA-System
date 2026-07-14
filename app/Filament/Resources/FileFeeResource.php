<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileFeeResource\Pages;
use App\Models\FileFee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Grouping\Group;

class FileFeeResource extends Resource
{
    protected static ?string $model = FileFee::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255)
                    ->helperText('Optional label to identify this fee entry.'),
                Forms\Components\Select::make('fee_mode')
                    ->label('Fee type')
                    ->options([
                        'tier_package' => 'Tier package (Standard / Middle / Complex)',
                        'service_type' => 'Service type fee',
                    ])
                    ->default('tier_package')
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?FileFee $record) {
                        if (! $record) {
                            return;
                        }

                        $component->state($record->isTierPackage() ? 'tier_package' : 'service_type');
                    }),
                Forms\Components\Section::make('Tier package')
                    ->schema([
                        Forms\Components\TextInput::make('simple_amount')
                            ->label('Standard (Simple)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€'),
                        Forms\Components\TextInput::make('middle_amount')
                            ->label('Middle')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€'),
                        Forms\Components\TextInput::make('complex_amount')
                            ->label('Complex')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€'),
                        Forms\Components\TextInput::make('simple_max_total')
                            ->label('Standard cap')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€')
                            ->helperText('Bill total below this uses Standard. Defaults to 350 when empty.'),
                        Forms\Components\TextInput::make('middle_max_total')
                            ->label('Middle cap')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€')
                            ->helperText('Bill total below this uses Middle; otherwise Complex. Defaults to 1000 when empty.'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('fee_mode') === 'tier_package'),
                Forms\Components\Section::make('Service type fee')
                    ->schema([
                        Forms\Components\Select::make('service_type_id')
                            ->relationship('serviceType', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('fee_mode') === 'service_type'),
                Forms\Components\Section::make('Scope')
                    ->schema([
                        Forms\Components\Select::make('countries')
                            ->relationship('countries', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->live()
                            ->helperText('Leave empty to apply to all countries.'),
                        Forms\Components\Select::make('cities')
                            ->relationship(
                                'cities',
                                'name',
                                fn ($query, Get $get) => $query->when(
                                    filled($get('countries')),
                                    fn ($query) => $query->whereIn('country_id', $get('countries')),
                                ),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Leave empty to apply to all cities in the selected countries.'),
                        Forms\Components\Select::make('clients')
                            ->relationship('clients', 'company_name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Leave empty to apply to all clients.'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('name')->label('Name')->collapsible(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['countries', 'cities', 'clients', 'serviceType']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('fee_kind')
                    ->label('Type')
                    ->state(fn (FileFee $record) => $record->isTierPackage()
                        ? 'Tier package'
                        : ($record->serviceType?->name ?? 'Service type')),
                Tables\Columns\TextColumn::make('countries.name')
                    ->label('Countries')
                    ->badge()
                    ->placeholder('All countries'),
                Tables\Columns\TextColumn::make('cities.name')
                    ->label('Cities')
                    ->badge()
                    ->placeholder('All cities'),
                Tables\Columns\TextColumn::make('clients.company_name')
                    ->label('Clients')
                    ->badge()
                    ->placeholder('All clients'),
                Tables\Columns\TextColumn::make('simple_amount')
                    ->label('Standard')
                    ->money('eur')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('middle_amount')
                    ->label('Middle')
                    ->money('eur')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('complex_amount')
                    ->label('Complex')
                    ->money('eur')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tier_caps')
                    ->label('Caps')
                    ->state(function (FileFee $record) {
                        if (! $record->isTierPackage()) {
                            return null;
                        }

                        $caps = $record->tierCaps();

                        return '< ' . number_format($caps['simple_max'], 0) . ' / < ' . number_format($caps['middle_max'], 0);
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Service amount')
                    ->money('eur')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('tier_package')
                    ->label('Tier packages')
                    ->query(fn ($query) => $query
                        ->whereNull('service_type_id')
                        ->where(function ($query) {
                            $query->whereNotNull('simple_amount')
                                ->orWhereNotNull('middle_amount')
                                ->orWhereNotNull('complex_amount');
                        })),
                Tables\Filters\Filter::make('service_type')
                    ->label('Service type fees')
                    ->query(fn ($query) => $query->whereNotNull('service_type_id')),
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
