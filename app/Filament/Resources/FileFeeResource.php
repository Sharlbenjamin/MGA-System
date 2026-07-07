<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileFeeResource\Pages;
use App\Models\FileFee;
use Filament\Forms;
use Filament\Forms\Form;
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
                Forms\Components\Select::make('tier')
                    ->label('Fee tier')
                    ->options([
                        FileFee::TIER_SIMPLE => 'Simple',
                        FileFee::TIER_MIDDLE => 'Middle',
                        FileFee::TIER_COMPLEX => 'Complex',
                    ])
                    ->nullable()
                    ->helperText('Use for auto-invoice tier fees (Simple / Middle / Complex). Leave empty when using a service type.'),
                Forms\Components\Select::make('service_type_id')
                    ->relationship('serviceType', 'name')
                    ->nullable()
                    ->helperText('Use for operational service fees (e.g. House Call). Leave empty when using a tier.'),
                Forms\Components\Select::make('countries')
                    ->relationship('countries', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Leave empty to apply to all countries.'),
                Forms\Components\Select::make('clients')
                    ->relationship('clients', 'company_name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Leave empty to apply to all clients.'),
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
            ->groups([
                Group::make('tier')->label('Tier')->collapsible(),
                Group::make('serviceType.name')->label('Service Type')->collapsible(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['countries', 'clients', 'serviceType']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tier')
                    ->label('Tier')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('serviceType', fn ($query) => $query->where('name', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('countries.name')
                    ->label('Countries')
                    ->badge()
                    ->placeholder('All countries'),
                Tables\Columns\TextColumn::make('clients.company_name')
                    ->label('Clients')
                    ->badge()
                    ->placeholder('All clients'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('eur')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->options([
                        FileFee::TIER_SIMPLE => 'Simple',
                        FileFee::TIER_MIDDLE => 'Middle',
                        FileFee::TIER_COMPLEX => 'Complex',
                    ]),
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
