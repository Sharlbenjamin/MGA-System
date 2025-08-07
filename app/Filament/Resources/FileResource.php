<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileResource\Pages;
use App\Filament\Resources\FileResource\RelationManagers\GopRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\MedicalReportRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\PrescriptionRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\PatientRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\TaskRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\BankAccountRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\BillRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\InvoiceRelationManager;
use App\Models\Client;
use App\Models\Country;
use App\Models\File;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationGroup = 'Operation';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $recordTitleAttribute = 'mga_reference';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['New', 'Handling', 'Available', 'Confirmed', 'Hold'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Checkbox::make('new_patient')->label('New Patient')->default(true)->live()
                ->disabled(fn ($context) => $context === 'edit'),
            Select::make('patient_id')
                ->relationship(
                    'patient',
                    'name',
                    fn ($query) => $query->whereHas('client', fn ($q) => $q->where('status', 'Active'))
                )
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} - {$record->client->company_name}")
                ->label('Patient')
                ->required()
                ->live(onBlur: false)
                ->searchable()
                ->preload()
                ->disabled(fn ($context) => $context === 'edit')
                ->dehydrated()
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('mga_reference', File::generateMGAReference($state, 'patient'));
                })->hidden(fn ($get) => $get('new_patient') == true),
            TextInput::make('patient_name')->label('Patient Name')->required()->hidden(fn ($get) => $get('new_patient') == false),
            DatePicker::make('patient_dob')->label('Date of Birth')->nullable()->hidden(fn ($get) => $get('new_patient') == false),
            Select::make('patient_gender')->label('Gender')->options(['Male' => 'Male', 'Female' => 'Female'])->nullable()->hidden(fn ($get) => $get('new_patient') == false),
            Select::make('client_id')->options(Client::where('status', 'Active')->pluck('company_name', 'id'))->searchable()->preload()->required()->live()->afterStateUpdated(function ($state, callable $set) {
                    $set('mga_reference', File::generateMGAReference($state, 'client'));
                })
                ->hidden(fn ($get) => !$get('new_patient')),
            TextInput::make('mga_reference')->label('MGA Reference')->required()->readOnly()->unique(ignoreRecord: true)->helperText('Auto-generated based on the patient'),
            Select::make('service_type_id')->relationship('serviceType', 'name')->label('Service Type')->required()->live(),
            TextInput::make('client_reference')->label('Client Reference')->nullable(),
            Select::make('country_id')->relationship('country', 'name')->label('Country')->preload()->searchable()->nullable()->live(),
            Select::make('city_id')->label('City')->searchable()->nullable()->options(fn ($get) => \App\Models\City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive(),
            Select::make('provider_branch_id')->label('Provider Branch')->searchable()->nullable()->options(fn ($get) => \App\Models\ProviderBranch::when($get('service_type_id') != 2, function ($query) use ($get) {
                return $query->whereHas('branchCities', fn ($q) => $q->where('city_id', $get('city_id')));
            })->where('service_types', 'like', '%' . \App\Models\ServiceType::find($get('service_type_id'))?->name . '%')->orderBy('priority', 'asc')->pluck('branch_name', 'id'))->reactive(),
            Select::make('status')->options(['New' => 'New','Handling' => 'Handling','Available' => 'Available', 'Confirmed' => 'Confirmed', 'Assisted' => 'Assisted','Hold' => 'Hold','Cancelled' => 'Cancelled','Void' => 'Void',])->default('New')->required(),
            DatePicker::make('service_date')->label('Service Date')->nullable(),
            TimePicker::make('service_time')->label('Service Time')->nullable(),
            TextInput::make('email')->label('Email')->email()->nullable(),
            TextInput::make('phone')->label('Phone')->tel()->nullable(),
            TextInput::make('address')->label('Address')->nullable(),
            Select::make('contact_patient')->label('Who will Contact the Patient?')->options(['Client' => 'Client','MGA' => 'MGA', 'Ask' => 'Ask'])->default('Client')->required(),
            Textarea::make('symptoms')->label('Symptoms')->nullable(),
            Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
        ]);
    }


    public static function table(Table $table): Table
    {
        // sort by service_date
        return $table->groups([
            Group::make('patient.client.company_name')->collapsible()->label('Client'),
            Group::make('status')->collapsible(),
            Group::make('country.name')->collapsible()->label('Country'),
            Group::make('serviceType.name')->collapsible()->label('Service Type'),
        ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'patient.client',
                'country',
                'city',
                'serviceType',
                'providerBranch.provider',
                'gops'
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                // Enhanced columns
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->description(fn ($record) => $record->client_reference ? "Ref: {$record->client_reference}" : null)
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->description(fn ($record) => 
                        $record->mga_reference . 
                        ($record->patient?->dob ? ' | DOB: ' . $record->patient->dob->format('d/m/Y') : '') .
                        ($record->patient?->gender ? ' | ' . $record->patient->gender : '')
                    )
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Location')
                    ->description(fn ($record) => $record->city?->name)
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service')
                    ->description(fn ($record) => 
                        ($record->service_time ? \Carbon\Carbon::parse($record->service_time)->format('H:i') . ' - ' : '') . 
                        ($record->serviceType?->name ?? 'No Service Type')
                    )
                    ->formatStateUsing(fn ($record) => 
                        $record->service_date ? $record->service_date->format('d/m/Y') : 'No Date'
                    )
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Provider')
                    ->description(fn ($record) => $record->providerBranch?->provider?->name)
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'New' => 'success',
                        'Handling' => 'info',
                        'Available' => 'info',
                        'Confirmed' => 'success',
                        'Assisted' => 'success',
                        'Hold' => 'warning',
                        'Cancelled' => 'danger',
                        'Void' => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('gops_count')
                    ->label('GOP')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '✓' : '✗')
                    ->counts('gops', fn ($query) => $query->where('type', 'In')->where('status', '=', 'Sent')),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('opened_cases')
                    ->label('Opened Cases Only')
                    ->default(true)
                    ->query(function (Builder $query) {
                        return $query->whereIn('status', ['New', 'Handling', 'Available', 'Confirmed', 'Hold']);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['opened_cases'] ?? false) {
                            return 'Opened Cases Only';
                        }
                        return null;
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                    ]),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(\App\Models\Country::pluck('name', 'id')),
                SelectFilter::make('city_id')
                    ->label('City')
                    ->options(\App\Models\City::pluck('name', 'id')),
                SelectFilter::make('service_type_id')
                    ->label('Service Type')
                    ->options(\App\Models\ServiceType::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id]))
                ->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('Edit')
                ->url(fn (File $record) => FileResource::getUrl('edit', ['record' => $record->id]))
                ->icon('heroicon-o-pencil'),

            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GopRelationManager::class,
            BillRelationManager::class,
            MedicalReportRelationManager::class,
            PrescriptionRelationManager::class,
            PatientRelationManager::class,
            CommentsRelationManager::class,
            AppointmentsRelationManager::class,
            TaskRelationManager::class,
            BankAccountRelationManager::class,
            InvoiceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => FileResource\Pages\ListFiles::route('/'),
            'create' => FileResource\Pages\CreateFile::route('/create'),
            'edit' => FileResource\Pages\EditFile::route('/{record}/edit'),
            'view' => FileResource\Pages\ViewFile::route('/{record}/show'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->mga_reference . ' - ' . ($record->patient?->name ?? 'Unknown Patient');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Patient' => $record->patient?->name ?? 'Unknown',
            'Client' => $record->patient?->client?->company_name ?? 'Unknown',
            'Status' => $record->status ?? 'Unknown',
            'Service Date' => $record->service_date?->format('d/m/Y') ?? 'Unknown',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['patient.client', 'country', 'city', 'serviceType']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return FileResource::getUrl('view', ['record' => $record]);
    }
}
