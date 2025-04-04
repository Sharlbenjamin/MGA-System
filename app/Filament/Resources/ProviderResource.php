<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\RelationManagers\BankAccountRelationManager;
use App\Filament\Resources\ProviderResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers\ProviderBranchRelationManager;
use App\Filament\Resources\ProviderResource\RelationManagers\ProviderLeadRelationManager;
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
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Contact;
class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationGroup = 'PRM';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-truck'; // 🚚 Providers Icon

    public static function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            Select::make('country_id')->label('Country')->options(Country::pluck('name', 'id'))->searchable()->required(),
            Select::make('status')->label('Status')->options([
                'Active' => 'Active',
                'Hold' => 'Hold',
                'Potential' => 'Potential',
                'Black List' => 'Black List',
            ])->required(),
            Select::make('type')->label('Provider Type')->options([
                'Doctor' => 'Doctor',
                'Hospital' => 'Hospital',
                'Clinic' => 'Clinic',
                'Dental' => 'Dental',
                'Agency' => 'Agency',
            ])->required(),
            TextInput::make('name')->label('Provider Name')->required()->maxLength(255),

            TextInput::make('payment_due')->label('Payment Due (Days)')->numeric()->minValue(0)->nullable(),
            Select::make('payment_method')->label('Payment Method')->options([
                'Online Link' => 'Online Link',
                'Bank Transfer' => 'Bank Transfer',
                'AEAT' => 'AEAT',
            ])->nullable(),
            Textarea::make('comment')->label('Comment')->nullable(),
            Select::make('gop_contact_id')->label('GOP Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
            Select::make('operation_contact_id')->label('Operation Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
            Select::make('financial_contact_id')->label('Financial Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
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
                    'info' => 'Potential',
                    'red' => 'Black List',
                ])
                ->sortable(),
            TextColumn::make('type')->label('Provider Type')->sortable(),
            TextColumn::make('leads_count')->label('Leads')->counts('leads'),
            TextColumn::make('latestLead.last_contact_date')->label('Last Contact')->date('d-m-Y'),
        ])
        ->filters([
            SelectFilter::make('status')->multiple()
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                        'Potential' => 'Potential',
                        'Black List' => 'Black List',
                    ])->label('Filter by Status')->attribute('status'),
            //country filter
            SelectFilter::make('country_id')->multiple()
                    ->options(Country::pluck('name', 'id'))->label('Filter by Country')->attribute('country_id'),
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
            ProviderLeadRelationManager::class,
            ProviderBranchRelationManager::class,
            BankAccountRelationManager::class,
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