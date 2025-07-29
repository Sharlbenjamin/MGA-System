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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
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
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->unique('providers', 'name', ignoreRecord: true),

                                Select::make('new_provider_type')
                                    ->label('Provider Type')
                                    ->options([
                                        'Doctor' => 'Doctor',
                                        'Hospital' => 'Hospital',
                                        'Clinic' => 'Clinic',
                                        'Dental' => 'Dental',
                                        'Agency' => 'Agency',
                                    ])
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Grid::make(2)
                            ->schema([
                                Select::make('new_provider_country_id')
                                    ->label('Country')
                                    ->options(Country::pluck('name', 'id'))
                                    ->searchable()
                                    ->reactive()
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('cities', []);
                                    }),

                                Select::make('new_provider_status')
                                    ->label('Status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Hold' => 'Hold',
                                        'Potential' => 'Potential',
                                        'Black list' => 'Black List',
                                    ])
                                    ->required(fn (Get $get) => $get('create_new_provider'))
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_provider_email')
                                    ->label('Provider Email')
                                    ->email()
                                    ->unique('providers', 'email', ignoreRecord: true)
                                    ->visible(fn (Get $get) => $get('create_new_provider'))
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $email = $get('new_provider_email');
                                        if ($email) {
                                            $exists = Provider::where('email', $email)->exists();
                                            if ($exists) {
                                                $set('new_provider_email', null);
                                                Notification::make()
                                                    ->title('Email Already Exists')
                                                    ->body('This email is already registered with another provider.')
                                                    ->danger()
                                                    ->send();
                                            }
                                        }
                                    }),

                                TextInput::make('new_provider_phone')
                                    ->label('Provider Phone')
                                    ->tel()
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('new_provider_payment_due')
                                    ->label('Payment Due (Days)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get) => $get('create_new_provider')),

                                Select::make('new_provider_payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'Online Link' => 'Online Link',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'AEAT' => 'AEAT',
                                    ])
                                    ->visible(fn (Get $get) => $get('create_new_provider')),
                            ])
                            ->visible(fn (Get $get) => $get('create_new_provider')),

                        TextInput::make('new_provider_comment')
                            ->label('Provider Comment')
                            ->visible(fn (Get $get) => $get('create_new_provider')),
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
                            ->required(),

                        Toggle::make('all_country')
                            ->label('All Country')
                            ->inline(),

                        Select::make('province_id')
                            ->label('Province')
                            ->options(function (Get $get) {
                                $providerId = $get('provider_id');
                                $countryId = $get('new_provider_country_id');
                                
                                if ($providerId) {
                                    $provider = Provider::find($providerId);
                                    return $provider ? Province::where('country_id', $provider->country_id)->pluck('name', 'id') : [];
                                } elseif ($countryId) {
                                    return Province::where('country_id', $countryId)->pluck('name', 'id');
                                }
                                
                                return [];
                            })
                            ->searchable()
                            ->reactive(),

                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(['Active' => 'Active','Hold' => 'Hold',])
                                    ->required(),

                                Select::make('priority')
                                    ->label('Priority')
                                    ->options([
                                        '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
                                        '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
                                    ])
                                    ->required(),
                            ]),

                        Select::make('service_types')
                            ->label('Service Types')
                            ->multiple()
                            ->options(ServiceType::pluck('name', 'name'))
                            ->searchable()
                            ->required(),

                        Grid::make(3)
                            ->schema([
                                Select::make('gop_contact_id')
                                    ->label('GOP Contact')
                                    ->options(Contact::pluck('title', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Select::make('operation_contact_id')
                                    ->label('Operation Contact')
                                    ->options(Contact::pluck('title', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Select::make('financial_contact_id')
                                    ->label('Financial Contact')
                                    ->options(Contact::pluck('title', 'id'))
                                    ->searchable()
                                    ->nullable(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('day_cost')
                                    ->label('Day Cost')
                                    ->numeric()
                                    ->nullable(),

                                TextInput::make('night_cost')
                                    ->label('Night Cost')
                                    ->numeric()
                                    ->nullable(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('weekend_cost')
                                    ->label('Weekend Cost')
                                    ->numeric()
                                    ->nullable(),

                                TextInput::make('weekend_night_cost')
                                    ->label('Weekend Night Cost')
                                    ->numeric()
                                    ->nullable(),
                            ]),

                        Section::make('Medical Services')
                            ->schema([
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
                TextColumn::make('service_types')->label('Service Types')->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)->sortable(),
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
                // Add filters if needed
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