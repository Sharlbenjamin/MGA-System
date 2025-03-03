<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileResource\Pages;
use App\Filament\Resources\FileResource\RelationManagers\GopRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\MedicalReportRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\PrescriptionRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\PatientRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\AppointmentsRelationManager;

use App\Models\Country;
use App\Models\File;
use App\Models\Patient;
use Filament\Forms;
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
use Filament\Tables\Filters\SelectFilter;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationGroup = 'Operation';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list'; // 📋 Files Icon

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('patient_id')->relationship('patient', 'name')->label('Patient')->required()->live()
            ->afterStateUpdated(function ($state, callable $set, $livewire) {
                // Only generate a new reference if it's empty (new file)
                if (empty($livewire->data['mga_reference'])) {
                    $set('mga_reference', self::generateMGAReference($state));
                }
            }),
            TextInput::make('mga_reference')->label('MGA Reference')->required()->readOnly()->unique(ignoreRecord: true)->helperText('Auto-generated when you chose the patient'),
            Select::make('service_type_id')->relationship('serviceType', 'name')->label('Service Type')->required()->live(),
            Select::make('status')->options(['New' => 'New','Handling' => 'Handling','In Progress' => 'In Progress','Assisted' => 'Assisted','Hold' => 'Hold','Void' => 'Void',])->default('New')->required(),
            TextInput::make('client_reference')->label('Client Reference')->nullable(),
            Select::make('country_id')->relationship('country', 'name')->label('Country')->searchable()->nullable()->live(),
            Select::make('city_id')->label('City')->searchable()->nullable()->options(fn ($get) => \App\Models\City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive(),
            Select::make('provider_branch_id')->label('Provider Branch')->searchable()->nullable()
            ->options(fn ($get) => \App\Models\ProviderBranch::where('city_id', $get('city_id'))
                ->where('service_type_id', $get('service_type_id')) // Match both fields
                ->pluck('branch_name', 'id'))
            ->reactive(),
            DatePicker::make('service_date')->label('Service Date')->nullable(),
            TimePicker::make('service_time')->label('Service Time')->nullable(),
            TextInput::make('address')->label('Address')->nullable(),
            Textarea::make('symptoms')->label('Symptoms')->nullable(),
            Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable(),
                // Relationship columns
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('serviceType.name')->label('Service Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch')->sortable()->searchable(),
                // Date & Time columns
                Tables\Columns\TextColumn::make('service_date')->date()->sortable(),
            ])
            ->filters([
                // Filter by status
                SelectFilter::make('status')
                    ->options([
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'In Progress' => 'In Progress',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Void' => 'Void',
                    ]),
                SelectFilter::make('country_id')
                    ->options(\App\Models\Country::pluck('name', 'id'))
                    ->label('Country'),
    
                SelectFilter::make('city_id')
                    ->options(\App\Models\City::pluck('name', 'id'))
                    ->label('City'),
    
                SelectFilter::make('service_type_id')
                    ->options(\App\Models\ServiceType::pluck('name', 'id'))
                    ->label('Service Type'),
                
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id])) 
                ->icon('heroicon-o-eye')
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GopRelationManager::class, // Registers the Medical Reports table
            MedicalReportRelationManager::class, // Registers the Medical Reports table
            PrescriptionRelationManager::class, // Registers the Medical Reports table
            PatientRelationManager::class,
            CommentsRelationManager::class,
            AppointmentsRelationManager::class,
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

    public static function generateMGAReference($patientId)
    {
        if (!$patientId) return 'MG000XXX';
    
        $patient = \App\Models\Patient::find($patientId);
        if (!$patient || !$patient->client) return 'MG000XXX';
    
        return sprintf('MG%03d%s', $patient->client->files()->count() + 1, $patient->client->initials ?? '');
    }

}
