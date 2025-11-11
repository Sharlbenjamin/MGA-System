<?php

namespace App\Filament\Resources\CityResource\RelationManagers;

use App\Filament\Resources\FileResource;
use App\Models\File;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class FileRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $model = File::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('patient_id')
                ->relationship(
                    'patient',
                    'name',
                    fn ($query) => $query->whereHas('client', fn ($q) => $q->where('status', 'Active'))
                )
                ->required()
                ->label('Patient')
                ->live()
                ->afterStateUpdated(fn ($get, $set) => $get('patient_id') ? $set('mga_reference', File::generateMGAReference($get('patient_id'), 'patient')) : null),
            Forms\Components\TextInput::make('mga_reference')
                ->label('MGA Reference')
                ->required()
                ->readOnly()
                ->default(fn ($get) => $get('patient_id') ? File::generateMGAReference($get('patient_id'), 'patient') : null)
                ->unique(ignoreRecord: true)
                ->helperText('Auto-generated based on the patient')
                ->dehydrated(),
            Forms\Components\Select::make('service_type_id')
                ->relationship('serviceType', 'name')
                ->label('Service Type')
                ->required()
                ->live(),
            Forms\Components\Select::make('status')
                ->options([
                    'New' => 'New',
                    'Handling' => 'Handling',
                    'Available' => 'Available',
                    'Confirmed' => 'Confirmed',
                    'Assisted' => 'Assisted',
                    'Hold' => 'Hold',
                    'Cancelled' => 'Cancelled',
                    'Void' => 'Void',
                ])
                ->default('New')
                ->required(),
            Forms\Components\TextInput::make('client_reference')
                ->label('Client Reference')
                ->nullable(),
            Forms\Components\Select::make('country_id')
                ->relationship('country', 'name')
                ->label('Country')
                ->preload()
                ->searchable()
                ->nullable()
                ->live(),
            Forms\Components\Select::make('city_id')
                ->label('City')
                ->searchable()
                ->nullable()
                ->default(fn () => $this->ownerRecord->id)
                ->disabled()
                ->dehydrated()
                ->options(fn ($get) => \App\Models\City::where('country_id', $get('country_id'))->pluck('name', 'id'))
                ->reactive(),
            Forms\Components\Select::make('provider_branch_id')
                ->label('Provider Branch')
                ->searchable()
                ->nullable()
                ->options(fn ($get) => \App\Models\ProviderBranch::when($get('service_type_id') != 2, function ($query) use ($get) {
                    return $query->where('city_id', $get('city_id'));
                })->when($get('service_type_id'), function ($query) use ($get) {
                    return $query->whereHas('services', function ($q) use ($get) {
                        $q->where('service_type_id', $get('service_type_id'));
                    });
                })->orderBy('priority', 'asc')->pluck('branch_name', 'id'))
                ->reactive(),
            Forms\Components\DatePicker::make('service_date')
                ->label('Service Date')
                ->nullable(),
            Forms\Components\TimePicker::make('service_time')
                ->label('Service Time')
                ->nullable(),
            Forms\Components\TextInput::make('address')
                ->label('Address')
                ->nullable(),
            Forms\Components\Textarea::make('symptoms')
                ->label('Symptoms')
                ->nullable(),
            Forms\Components\Textarea::make('diagnosis')
                ->label('Diagnosis')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['tasks as undone_tasks_count' => function ($query) {
                $query->where('is_done', false);
            }]))
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')
                    ->sortable()
                    ->searchable()
                    ->label('MGA Reference'),
                Tables\Columns\TextColumn::make('client_reference')
                    ->sortable()
                    ->searchable()
                    ->label('Client Reference'),
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
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Provider Branch')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->date()
                    ->sortable()
                    ->label('Service Date'),
                Tables\Columns\TextColumn::make('undone_tasks_count')
                    ->label('Undone Tasks')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
            ])
            ->filters([
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
                    ])
                    ->label('Status'),
                SelectFilter::make('country_id')
                    ->options(\App\Models\Country::pluck('name', 'id'))
                    ->label('Country'),
                SelectFilter::make('service_type_id')
                    ->options(\App\Models\ServiceType::pluck('name', 'id'))
                    ->label('Service Type'),
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                    ->label('View File')
                    ->icon('heroicon-o-eye')
                    ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('Edit')
                    ->url(fn (File $record) => FileResource::getUrl('edit', ['record' => $record->id]))
                    ->icon('heroicon-o-pencil'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
}

