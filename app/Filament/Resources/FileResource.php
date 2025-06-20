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
        return static::getModel()::count();
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
            Select::make('client_id')->options(Client::where('status', 'Active')->pluck('company_name', 'id'))->searchable()->preload()->required()->live()->afterStateUpdated(function ($state, callable $set) {
                    $set('mga_reference', File::generateMGAReference($state, 'client'));
                })
                ->hidden(fn ($get) => !$get('new_patient')),
            TextInput::make('mga_reference')->label('MGA Reference')->required()->readOnly()->unique(ignoreRecord: true)->helperText('Auto-generated based on the patient'),
            Select::make('service_type_id')->relationship('serviceType', 'name')->label('Service Type')->required()->live(),
            Select::make('status')->options(['New' => 'New','Handling' => 'Handling','Available' => 'Available', 'Confirmed' => 'Confirmed', 'Assisted' => 'Assisted','Hold' => 'Hold','Cancelled' => 'Cancelled','Void' => 'Void',])->default('New')->required(),
            TextInput::make('client_reference')->label('Client Reference')->nullable(),
            Select::make('contact_patient')->label('Who will Contact the Patient?')->options(['Client' => 'Client','MGA' => 'MGA', 'Ask' => 'Ask'])->default('Client')->required(),
            Select::make('country_id')->relationship('country', 'name')->label('Country')->preload()->searchable()->nullable()->live(),
            Select::make('city_id')->label('City')->searchable()->nullable()->options(fn ($get) => \App\Models\City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive(),
            Select::make('provider_branch_id')->label('Provider Branch')->searchable()->nullable()->options(fn ($get) => \App\Models\ProviderBranch::when($get('service_type_id') != 2, function ($query) use ($get) {
                return $query->whereHas('branchCities', fn ($q) => $q->where('city_id', $get('city_id')));
            })->where('service_types', 'like', '%' . \App\Models\ServiceType::find($get('service_type_id'))?->name . '%')->orderBy('priority', 'asc')->pluck('branch_name', 'id'))->reactive(),
            DatePicker::make('service_date')->label('Service Date')->nullable(),
            TimePicker::make('service_time')->label('Service Time')->nullable(),
            TextInput::make('address')->label('Address')->nullable(),
            Textarea::make('symptoms')->label('Symptoms')->nullable(),
            Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
        ]);
    }


    public static function table(Table $table): Table
    {
        // sort by service_date
        return $table->groups([
            Group::make('patient.client.company_name')->collapsible(),
            Group::make('status')->collapsible(),
            Group::make('country.name')->collapsible(),
            Group::make('city.name')->collapsible(),
            Group::make('serviceType.name')->collapsible(),
            Group::make('providerBranch.branch_name')->collapsible(),
        ])
            ->modifyQueryUsing(fn ($query) => $query->withCount(['tasks as undone_tasks_count' => function ($query) {
                $query->where('is_done', false);
            }]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('patient.client.company_name')->label('Client')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('mga_reference')->sortable()->searchable()->summarize(Count::make()),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable()->badge()->color(fn ($state) => match ($state) {
                    'New' => 'success',
                    'Handling' => 'info',
                    'Available' => 'info',
                    'Confirmed' => 'success',
                    'Assisted' => 'success',
                    'Hold' => 'warning',
                    'Cancelled' => 'danger',
                    'Void' => 'gray',
                }),
                // Relationship columns
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('serviceType.name')->label('Service Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('contact_patient')->label('Contact Patient')->sortable()->searchable(),
                // Date & Time columns
                Tables\Columns\TextColumn::make('service_date')->date()->sortable(),
                // count undone tasks
                Tables\Columns\TextColumn::make('undone_tasks_count')->label('Undone Tasks')->badge()->color('warning')->sortable(),
            ])
            ->filters([
                // Filter by status
                SelectFilter::make('status')
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
                SelectFilter::make('country_id')->options(\App\Models\Country::pluck('name', 'id'))->label('Country'),
                SelectFilter::make('city_id')->options(\App\Models\City::pluck('name', 'id'))->label('City'),
                SelectFilter::make('service_type_id')->options(\App\Models\ServiceType::pluck('name', 'id'))->label('Service Type'),
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
            MedicalReportRelationManager::class,
            PrescriptionRelationManager::class,
            PatientRelationManager::class,
            CommentsRelationManager::class,
            AppointmentsRelationManager::class,
            TaskRelationManager::class,
            BankAccountRelationManager::class,
            BillRelationManager::class,
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

}
