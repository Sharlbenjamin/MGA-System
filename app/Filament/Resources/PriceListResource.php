<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceListResource\Pages;
use App\Models\PriceList;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\ProviderBranch;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-euro';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        // Left Column - Basic Information
                        Section::make('Basic Information')
                            ->schema([
                                Select::make('country_id')
                                    ->label('Country')
                                    ->options(Country::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                                Select::make('city_id')
                                    ->label('City')
                                    ->options(function (Get $get) {
                                        $countryId = $get('country_id');
                                        if (!$countryId) return [];
                                        return City::where('country_id', $countryId)->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('provider_branch_id', null)),

                                Select::make('service_type_id')
                                    ->label('Service Type')
                                    ->options(ServiceType::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Select::make('provider_branch_id')
                                    ->label('Provider Branch (Optional)')
                                    ->options(function (Get $get) {
                                        $countryId = $get('country_id');
                                        $cityId = $get('city_id');
                                        $serviceTypeId = $get('service_type_id');
                                        
                                        if (!$countryId || !$cityId || !$serviceTypeId) return [];
                                        
                                        $serviceTypeName = ServiceType::find($serviceTypeId)?->name;
                                        
                                        return ProviderBranch::query()
                                            ->where('city_id', $cityId)
                                            ->where('status', 'Active')
                                            ->whereJsonContains('service_types', $serviceTypeName)
                                            ->with('provider')
                                            ->get()
                                            ->mapWithKeys(function ($branch) {
                                                return [$branch->id => $branch->provider->name . ' - ' . $branch->branch_name];
                                            });
                                    })
                                    ->searchable()
                                    ->placeholder('Select for provider-specific pricing')
                                    ->live(),
                            ])
                            ->columnSpan(1),

                        // Middle Column - Pricing Information
                        Section::make('Pricing Information')
                            ->schema([
                                TextInput::make('day_price')
                                    ->label('Day Price (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->minValue(0),

                                TextInput::make('weekend_price')
                                    ->label('Weekend Price (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->minValue(0),

                                TextInput::make('night_weekday_price')
                                    ->label('Night Weekday Price (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->minValue(0),

                                TextInput::make('night_weekend_price')
                                    ->label('Night Weekend Price (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->minValue(0),

                                TextInput::make('suggested_markup')
                                    ->label('Suggested Markup')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(1)
                                    ->default(1.25)
                                    ->helperText('e.g., 1.25 = 25% markup')
                                    ->suffix('x'),
                            ])
                            ->columnSpan(1),

                        // Right Column - Provider Cost Helper & Actions
                        Section::make('Provider Cost Helper')
                            ->schema([
                                // Provider Cost Information Card
                                Card::make()
                                    ->schema([
                                        Placeholder::make('provider_cost_info')
                                            ->label('Average Provider Costs')
                                            ->content(function (Get $get) {
                                                $countryId = $get('country_id');
                                                $cityId = $get('city_id');
                                                $serviceTypeId = $get('service_type_id');
                                                
                                                if (!$countryId || !$cityId || !$serviceTypeId) {
                                                    return 'Select Country, City, and Service Type to see provider costs.';
                                                }
                                                
                                                $serviceTypeName = ServiceType::find($serviceTypeId)?->name;
                                                
                                                $branches = ProviderBranch::query()
                                                    ->where('city_id', $cityId)
                                                    ->where('status', 'Active')
                                                    ->whereJsonContains('service_types', $serviceTypeName)
                                                    ->get();
                                                
                                                if ($branches->isEmpty()) {
                                                    return 'No active provider branches found for the selected criteria.';
                                                }
                                                
                                                $avgDayCost = round($branches->avg('day_cost'), 2);
                                                $avgWeekendCost = round($branches->avg('weekend_cost'), 2);
                                                $avgNightCost = round($branches->avg('night_cost'), 2);
                                                $avgWeekendNightCost = round($branches->avg('weekend_night_cost'), 2);
                                                
                                                return view('components.provider-cost-info', [
                                                    'branches' => $branches,
                                                    'avgDayCost' => $avgDayCost,
                                                    'avgWeekendCost' => $avgWeekendCost,
                                                    'avgNightCost' => $avgNightCost,
                                                    'avgWeekendNightCost' => $avgWeekendNightCost,
                                                ])->render();
                                            }),
                                    ]),

                                // Note: Auto-suggest functionality moved to form actions
                                TextInput::make('helper_text')
                                    ->label('Helper')
                                    ->disabled()
                                    ->default('Use the "Auto-suggest Prices" action in the form header to calculate prices based on provider costs.')
                                    ->dehydrated(false),
                            ])
                            ->columnSpan(1),
                    ]),

                // Bottom Section - Notes
                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('final_price_notes')
                            ->label('Price Notes')
                            ->placeholder('Add any notes about pricing decisions, special conditions, etc.')
                            ->rows(3),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->headerActions([
                \Filament\Forms\Components\Actions\Action::make('suggest_prices')
                    ->label('Auto-suggest Prices')
                    ->icon('heroicon-o-light-bulb')
                    ->color('warning')
                    ->action(function (Get $get, Set $set) {
                        $providerBranchId = $get('provider_branch_id');
                        $markup = $get('suggested_markup') ?: 1.25;
                        
                        if ($providerBranchId) {
                            $branch = ProviderBranch::find($providerBranchId);
                            if ($branch) {
                                if ($branch->day_cost) {
                                    $set('day_price', round($branch->day_cost * $markup, 2));
                                }
                                if ($branch->weekend_cost) {
                                    $set('weekend_price', round($branch->weekend_cost * $markup, 2));
                                }
                                if ($branch->night_cost) {
                                    $set('night_weekday_price', round($branch->night_cost * $markup, 2));
                                }
                                if ($branch->weekend_night_cost) {
                                    $set('night_weekend_price', round($branch->weekend_night_cost * $markup, 2));
                                }
                                
                                Notification::make()
                                    ->title('Prices suggested successfully')
                                    ->success()
                                    ->send();
                            }
                        } else {
                            // Use average costs
                            $countryId = $get('country_id');
                            $cityId = $get('city_id');
                            $serviceTypeId = $get('service_type_id');
                            
                            if ($countryId && $cityId && $serviceTypeId) {
                                $serviceTypeName = ServiceType::find($serviceTypeId)?->name;
                                
                                $branches = ProviderBranch::query()
                                    ->where('city_id', $cityId)
                                    ->where('status', 'Active')
                                    ->whereJsonContains('service_types', $serviceTypeName)
                                    ->get();
                                
                                if ($branches->isNotEmpty()) {
                                    $avgDayCost = round($branches->avg('day_cost'), 2);
                                    $avgWeekendCost = round($branches->avg('weekend_cost'), 2);
                                    $avgNightCost = round($branches->avg('night_cost'), 2);
                                    $avgWeekendNightCost = round($branches->avg('weekend_night_cost'), 2);
                                    
                                    if ($avgDayCost > 0) {
                                        $set('day_price', round($avgDayCost * $markup, 2));
                                    }
                                    if ($avgWeekendCost > 0) {
                                        $set('weekend_price', round($avgWeekendCost * $markup, 2));
                                    }
                                    if ($avgNightCost > 0) {
                                        $set('night_weekday_price', round($avgNightCost * $markup, 2));
                                    }
                                    if ($avgWeekendNightCost > 0) {
                                        $set('night_weekend_price', round($avgWeekendNightCost * $markup, 2));
                                    }
                                    
                                    Notification::make()
                                        ->title('Prices suggested based on average provider costs')
                                        ->success()
                                        ->send();
                                }
                            }
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Auto-suggest Prices')
                    ->modalDescription('This will calculate suggested prices based on provider costs with the specified markup. Current prices will be overwritten.')
                    ->modalSubmitActionLabel('Suggest Prices'),

                \Filament\Forms\Components\Actions\Action::make('clear_prices')
                    ->label('Clear All Prices')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->action(function (Set $set) {
                        $set('day_price', null);
                        $set('weekend_price', null);
                        $set('night_weekday_price', null);
                        $set('night_weekend_price', null);
                        
                        Notification::make()
                            ->title('All prices cleared')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Clear All Prices')
                    ->modalDescription('This will clear all price fields. This action cannot be undone.')
                    ->modalSubmitActionLabel('Clear Prices'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('city.name')
                    ->label('City')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('providerBranch.branch_name')
                    ->label('Provider Branch')
                    ->sortable()
                    ->searchable()
                    ->placeholder('General pricing'),

                TextColumn::make('day_price')
                    ->label('Day Price')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('weekend_price')
                    ->label('Weekend Price')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('night_weekday_price')
                    ->label('Night Weekday')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('night_weekend_price')
                    ->label('Night Weekend')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                BadgeColumn::make('suggested_markup')
                    ->label('Markup')
                    ->formatStateUsing(fn ($state) => $state ? (($state - 1) * 100) . '%' : 'N/A')
                    ->colors(['success', 'warning'])
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('city_id')
                    ->label('City')
                    ->options(City::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('service_type_id')
                    ->label('Service Type')
                    ->options(ServiceType::pluck('name', 'id'))
                    ->searchable(),

                Filter::make('has_provider_branch')
                    ->label('Provider-specific pricing')
                    ->query(fn (Builder $query) => $query->whereNotNull('provider_branch_id')),

                Filter::make('no_provider_branch')
                    ->label('General pricing only')
                    ->query(fn (Builder $query) => $query->whereNull('provider_branch_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('country_id');
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
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['country', 'city', 'serviceType', 'providerBranch.provider']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
