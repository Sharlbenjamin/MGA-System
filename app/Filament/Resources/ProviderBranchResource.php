<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderBranchResource\Pages;
use App\Models\ProviderBranch;
use App\Models\Provider;
use App\Models\ServiceType;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class ProviderBranchResource extends Resource
{
    protected static ?string $model = ProviderBranch::class;


    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            TextInput::make('branch_name')
                ->label('Branch Name')
                ->required()
                ->maxLength(255), // ✅ Added new field

            Select::make('provider_id')
                ->label('Provider')
                ->options(Provider::pluck('name', 'id'))
                ->searchable()
                ->required(),

                Select::make('city_id')
                ->label('City')
                ->options(fn ($get) => 
                    City::where('country_id', Provider::where('id', $get('provider_id'))->value('country_id'))->pluck('name', 'id')
                )
                ->searchable()
                ->reactive()
                ->required(),

            Select::make('status')
                ->label('Status')
                ->options([
                    'Active' => 'Active',
                    'Hold' => 'Hold',
                ])
                ->required(),

            Select::make('priority')
                ->label('Priority')
                ->options([
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
                    '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
                ])
                ->required(),

            Select::make('service_type_id')
                ->label('Service Type')
                ->options(ServiceType::pluck('name', 'id'))
                ->searchable()
                ->required(),

            Select::make('communication_method')
                ->label('Communication Method')
                ->options(['Email' => 'Email', 'WhatsApp' => 'WhatsApp', 'Phone' => 'Phone'])
                ->required(),

            TextInput::make('day_cost')->label('Day Cost')->numeric()->nullable(),
            TextInput::make('night_cost')->label('Night Cost')->numeric()->nullable(),
            TextInput::make('weekend_cost')->label('Weekend Cost')->numeric()->nullable(),
            TextInput::make('weekend_night_cost')->label('Weekend Night Cost')->numeric()->nullable(),
        ]);
}

public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->columns([
            TextColumn::make('branch_name')->label('Branch Name')->sortable()->searchable(),

            TextColumn::make('provider.name')->label('Provider')->sortable()->searchable(),

            TextColumn::make('city.name')->label('City')->sortable()->searchable(),

            TextColumn::make('serviceType.name')->label('Service Type')->sortable(), // ✅ Fix: Show Service Type Name

            BadgeColumn::make('status')
                ->colors([
                    'success' => 'Active',
                    'warning' => 'Hold',
                ])
                ->sortable(),

            TextColumn::make('priority')->sortable(),

            TextColumn::make('communication_method')->label('Communication Method')->sortable(),

            TextColumn::make('day_cost')->label('Day Cost'),
            TextColumn::make('night_cost')->label('Night Cost'),
            TextColumn::make('weekend_cost')->label('Weekend Cost'),
            TextColumn::make('weekend_night_cost')->label('Weekend Night Cost'),
        ])
        ->filters([
            // Add filters if needed
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
            // Define any relationships here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderBranches::route('/'),
            'create' => Pages\CreateProviderBranch::route('/create'),
            'edit' => Pages\EditProviderBranch::route('/{record}/edit'),
        ];
    }
}