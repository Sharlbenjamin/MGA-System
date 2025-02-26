<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Models\Provider;
use App\Models\Country;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Resources\Resource;
use Filament\Forms\Get;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            Select::make('country_id')
                ->label('Country')
                ->options(Country::pluck('name', 'id'))
                ->searchable()
                ->required(),

            Select::make('status')
                ->label('Status')
                ->options([
                    'Active' => 'Active',
                    'Hold' => 'Hold',
                    'Potential' => 'Potential',
                    'Black List' => 'Black List',
                ])
                ->required(),

            Select::make('type')
                ->label('Provider Type')
                ->options([
                    'Doctor' => 'Doctor',
                    'Hospital' => 'Hospital',
                    'Clinic' => 'Clinic',
                    'Dental' => 'Dental',
                    'Agency' => 'Agency',
                ])
                ->required(),

            TextInput::make('name')
                ->label('Provider Name')
                ->required()
                ->maxLength(255),

            TextInput::make('payment_due')
                ->label('Payment Due (Days)')
                ->numeric()
                ->minValue(0)
                ->nullable(),

            Select::make('payment_method')
                ->label('Payment Method')
                ->options([
                    'Online Link' => 'Online Link',
                    'Bank Transfer' => 'Bank Transfer',
                    'AEAT' => 'AEAT',
                ])
                ->nullable(),

            Textarea::make('comment')
                ->label('Comment')
                ->nullable(),
        ]);
}

public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->columns([
            TextColumn::make('name')->label('Provider Name')->sortable()->searchable(),

            TextColumn::make('country.name')->label('Country')->sortable()->searchable(), // ✅ Fix: Show Country Name

            BadgeColumn::make('status')
                ->colors([
                    'success' => 'Active',
                    'warning' => 'Hold',
                    'gray' => 'Potential',
                    'red' => 'Black List',
                ])
                ->sortable(),

            TextColumn::make('type')->label('Provider Type')->sortable(),

            TextColumn::make('payment_due')->label('Payment Due (Days)')->sortable(),

            TextColumn::make('payment_method')->label('Payment Method')->sortable(),

            TextColumn::make('comment')->label('Comment')->limit(50),
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
            // Define relationships here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}