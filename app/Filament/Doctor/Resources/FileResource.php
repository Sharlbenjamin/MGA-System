<?php

namespace App\Filament\Doctor\Resources;

use App\Filament\Doctor\Resources\FileResource\Pages;
use App\Filament\Doctor\Resources\FileResource\RelationManagers;
use App\Models\File;
use App\Models\Client;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use App\Filament\Forms\Components\PatientNameInput;
use Illuminate\Support\Facades\Auth;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
            PatientNameInput::make('patient_name')->label('Patient Name')->required()->hidden(fn ($get) => $get('new_patient') == false),
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
            })->when($get('service_type_id'), function ($query) use ($get) {
                return $query->whereHas('services', function ($q) use ($get) {
                    $q->where('service_type_id', $get('service_type_id'));
                });
            })->orderBy('priority', 'asc')->pluck('branch_name', 'id'))->reactive(),
            Select::make('status')->options(['New' => 'New','Handling' => 'Handling','Available' => 'Available', 'Confirmed' => 'Confirmed', 'Assisted' => 'Assisted','Hold' => 'Hold','Cancelled' => 'Cancelled','Void' => 'Void',])->default('New')->required(),
            DatePicker::make('service_date')->label('Service Date')->nullable(),
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
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('mga_reference')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('patient.name')->label('Patient')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('patient.dob')->label('Date of Birth')->date()->sortable()->searchable(),
            Tables\Columns\TextColumn::make('patient.gender')->label('Gender')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('city.name')->label('City')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('serviceType.name')->label('Service Type')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('service_date')->date()->sortable(),
        ])
        ->filters([
            TernaryFilter::make('user_provider_only')
                ->label('Show Only My Files')
                ->trueLabel('Yes')
                ->falseLabel('No')
                ->query(fn (Builder $query, $state) =>
                    $state ? $query->whereHas('providerBranch.provider', fn ($q) =>
                        $q->where('name', Auth::user()->name)
                    ) : $query
                ),
            SelectFilter::make('patient_gender')
                ->label('Gender')
                ->options(['Male' => 'Male', 'Female' => 'Female'])
                ->query(fn (Builder $query, $state) =>
                    $state ? $query->whereHas('patient', fn ($q) => $q->where('gender', $state)) : $query
                ),
        ])
            ->actions([
                Tables\Actions\Action::make('View')
                ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id]))
                ->icon('heroicon-o-eye')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MedicalReportRelationManager::class, // Registers the Medical Reports table
            RelationManagers\PrescriptionRelationManager::class, // Registers the Medical Reports table
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiles::route('/'),
            'create' => Pages\CreateFile::route('/create'),
            'edit' => Pages\EditFile::route('/{record}/edit'),
            'view' => Pages\ViewFile::route('/{record}/show'),
        ];
    }
}
