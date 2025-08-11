<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderBranchResource\RelationManagers\BillRelationManager;
use App\Filament\Resources\ProviderBranchResource\Pages;
use App\Filament\Resources\ProviderBranchResource\RelationManagers\ContactRelationManager;
use App\Filament\Resources\ProviderBranchResource\RelationManagers\BankAccountRelationManager;
use App\Models\ProviderBranch;
use App\Models\Provider;
use App\Models\ServiceType;
use App\Models\City;
use App\Models\Contact;
use App\Models\Province;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class ProviderBranchResource extends Resource
{
    protected static ?string $model = ProviderBranch::class;

    protected static ?string $navigationGroup = 'PRM';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $recordTitleAttribute = 'branch_name';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Provider Information')
                    ->schema([
                        Toggle::make('create_new_provider')
                            ->label('Create New Provider')
                            ->default(false)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('provider_id', null);
                                $set('new_provider_name', null);
                                $set('new_provider_type', null);
                                $set('new_provider_country_id', null);
                                $set('new_provider_status', null);
                            }),

                        // Existing Provider Selection
                        Select::make('provider_id')
                            ->label('Select Provider')
                            ->options(Provider::pluck('name', 'id'))
                            ->searchable()
                            ->reactive()
                            ->visible(fn (Get $get) => !$get('create_new_provider'))
                            ->required(fn (Get $get) => !$get('create_new_provider'))
                            ->afterStateUpdated(fn (callable $set) => $set('cities', [])),

                        // New Provider Creation Fields
                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_provider_name')
                                    ->label('Provider Name')
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->maxLength(255),

                                Select::make('new_provider_type')
                                    ->label('Provider Type')
                                    ->options([
                                        'Doctor' => 'Doctor',
                                        'Hospital' => 'Hospital',
                                        'Clinic' => 'Clinic',
                                        'Dental' => 'Dental',
                                        'Agency' => 'Agency',
                                    ])
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->required(fn (Get $get) => $get('create_new_provider')),

                                Select::make('new_provider_country_id')
                                    ->label('Country')
                                    ->options(Country::pluck('name', 'id'))
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->reactive(),

                                Select::make('new_provider_status')
                                    ->label('Status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Hold' => 'Hold',
                                        'Potential' => 'Potential',
                                        'Black List' => 'Black List',
                                    ])
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->required(fn (Get $get) => $get('create_new_provider')),

                                TextInput::make('new_provider_email')
                                    ->label('Email')
                                    ->email()
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->nullable(),

                                TextInput::make('new_provider_phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->nullable(),

                                TextInput::make('new_provider_payment_due')
                                    ->label('Payment Due (Days)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->nullable(),

                                Select::make('new_provider_payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'Online Link' => 'Online Link',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'AEAT' => 'AEAT',
                                    ])
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->nullable(),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Textarea::make('new_provider_comment')
                            ->label('Comment')
                            ->visible(fn (Get $get) => $get('create_new_provider'))
                            ->nullable(),
                    ])
                    ->collapsible(),

                Section::make('Branch Information')
                    ->schema([
                        TextInput::make('branch_name')
                            ->label('Branch Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('cities')
                            ->label('Branch Cities')
                            ->multiple()
                            ->options(function (Get $get) {
                                $providerId = $get('provider_id');
                                $countryId = $get('new_provider_country_id');
                                
                                if ($providerId) {
                                    return City::query()
                                        ->whereIn('country_id', function($query) use ($providerId) {
                                            $query->select('country_id')
                                                ->from('providers')
                                                ->where('id', $providerId);
                                        })
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                } elseif ($countryId) {
                                    return City::where('country_id', $countryId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                }
                                
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->relationship('cities', 'name', function (Builder $query, Get $get) {
                                $providerId = $get('provider_id');
                                $countryId = $get('new_provider_country_id');
                                
                                if ($providerId) {
                                    $query->whereIn('country_id', function($subquery) use ($providerId) {
                                        $subquery->select('country_id')
                                            ->from('providers')
                                            ->where('id', $providerId);
                                    });
                                } elseif ($countryId) {
                                    $query->where('country_id', $countryId);
                                }
                            })
                            ->required(),

                        Toggle::make('all_country')
                            ->label('All Country')
                            ->inline(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'Active' => 'Active',
                                'Hold' => 'Hold',
                            ])
                            ->required(),

                        TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required(),

                        Select::make('communication_method')
                            ->label('Communication Method')
                            ->options([
                                'Email' => 'Email',
                                'WhatsApp' => 'WhatsApp',
                                'Phone' => 'Phone',
                            ])
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('day_cost')
                                    ->label('Day Cost')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->nullable(),

                                TextInput::make('night_cost')
                                    ->label('Night Cost')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->nullable(),

                                TextInput::make('weekend_cost')
                                    ->label('Weekend Cost')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->nullable(),

                                TextInput::make('weekend_night_cost')
                                    ->label('Weekend Night Cost')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->nullable(),
                            ]),

                        Section::make('Services')
                            ->schema([
                                Select::make('service_types')
                                    ->label('Service Types')
                                    ->options(ServiceType::pluck('name', 'name'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->formatStateUsing(function ($state) {
                                        if (is_string($state)) {
                                            return explode(',', $state);
                                        }
                                        return is_array($state) ? $state : [];
                                    })
                                    ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : []),

                                Grid::make(3)
                                    ->schema([
                                        Toggle::make('emergency')->label('Emergency')->inline(),
                                        Toggle::make('pediatrician_emergency')->label('Pediatrician Emergency')->inline(),
                                        Toggle::make('dental')->label('Dental')->inline(),
                                        Toggle::make('pediatrician')->label('Pediatrician')->inline(),
                                        Toggle::make('gynecology')->label('Gynecology')->inline(),
                                        Toggle::make('urology')->label('Urology')->inline(),
                                        Toggle::make('cardiology')->label('Cardiology')->inline(),
                                        Toggle::make('ophthalmology')->label('Ophthalmology')->inline(),
                                        Toggle::make('trauma_orthopedics')->label('Trauma / Orthopedics')->inline(),
                                        Toggle::make('surgery')->label('Surgery')->inline(),
                                        Toggle::make('intensive_care')->label('Intensive Care')->inline(),
                                        Toggle::make('obstetrics_delivery')->label('Obstetrics / Delivery')->inline(),
                                        Toggle::make('hyperbaric_chamber')->label('Hyperbaric Chamber')->inline(),
                                    ]),
                            ])
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('branch_name')->label('Branch Name')->sortable()->searchable(),
                TextColumn::make('provider.name')->label('Provider')->sortable()->searchable(),
                TextColumn::make('cities.name')->label('Cities')->sortable()->searchable(),
                TextColumn::make('service_types')
                    ->label('Service Types')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return implode(', ', $state);
                        }
                        return $state ?? 'No Service Types';
                    })
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Active',
                        'warning' => 'Hold',
                    ])
                    ->sortable(),

                TextColumn::make('priority')->sortable(),
                TextColumn::make('day_cost')->label('Day Cost'),
                TextColumn::make('night_cost')->label('Night Cost'),
                TextColumn::make('weekend_cost')->label('Weekend Cost'),
                TextColumn::make('weekend_night_cost')->label('Weekend Night Cost'),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('Provider')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('city')
                    ->label('City')
                    ->relationship('cities', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('country')
                    ->label('Country')
                    ->relationship('provider.country', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('service_type')
                    ->label('Service Type')
                    ->options(function () {
                        return ServiceType::pluck('name', 'name')->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->where(function ($q) use ($data) {
                                foreach ($data['values'] as $serviceType) {
                                    $q->orWhereJsonContains('service_types', $serviceType);
                                }
                            });
                        }
                        return $query;
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                    ]),
            ])
            ->groups([
                Group::make('provider.name')
                    ->label('Provider')
                    ->collapsible(),

                Group::make('cities.name')
                    ->label('City')
                    ->collapsible(),

                Group::make('provider.country.name')
                    ->label('Country')
                    ->collapsible(),

                Group::make('service_types')
                    ->label('Service Type')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function ($record) {
                        $serviceTypes = $record->service_types;
                        if (is_array($serviceTypes)) {
                            return implode(', ', $serviceTypes);
                        }
                        return $serviceTypes ?? 'No Service Types';
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('Overview')
                ->url(fn (ProviderBranch $record) => ProviderBranchResource::getUrl('overview', ['record' => $record]))->color('success'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContactRelationManager::class,
            BankAccountRelationManager::class,
            BillRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderBranches::route('/'),
            'create' => Pages\CreateProviderBranch::route('/create'),
            'edit' => Pages\EditProviderBranch::route('/{record}/edit'),
            'overview' => Pages\BranchOverView::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->branch_name ?? 'Unknown') . ' - ' . ($record->provider?->name ?? 'Unknown Provider');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Provider' => $record->provider?->name ?? 'Unknown',
            'Status' => $record->status ?? 'Unknown',
            'Priority' => $record->priority ?? 'Unknown',
            'Cities' => $record->cities?->pluck('name')->implode(', ') ?? 'Unknown',
            'Service Types' => is_array($record->service_types) ? implode(', ', $record->service_types) : ($record->service_types ?? 'Unknown'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['provider', 'cities']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ProviderBranchResource::getUrl('overview', ['record' => $record]);
    }

    public static function isGlobalSearchDisabled(): bool
    {
        return true;
    }
}