<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Resources\FileResource;
use App\Filament\Resources\FileResource\Pages;
use App\Models\Country;
use App\Models\File;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
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

class FileRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $model = File::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('patient_id')
                ->relationship(
                    'patient',
                    'name',
                    fn ($query) => $query->whereHas('client', fn ($q) => $q->where('status', 'Active'))
                )
                ->default($this->ownerRecord->id)
                ->disabled()
                ->dehydrated()
                ->label('Patient'),
            TextInput::make('mga_reference')
                ->label('MGA Reference')
                ->required()
                ->readOnly()
                ->default(fn () => File::generateMGAReference($this->ownerRecord->id))
                ->unique(ignoreRecord: true)
                ->helperText('Auto-generated based on the patient'),
            Select::make('service_type_id')->relationship('serviceType', 'name')->label('Service Type')->required()->live(),
            Select::make('status')->options(['New' => 'New','Handling' => 'Handling','Available' => 'Available', 'Confirmed' => 'Confirmed', 'Assisted' => 'Assisted','Hold' => 'Hold','Cancelled' => 'Cancelled','Void' => 'Void',])->default('New')->required(),
            TextInput::make('client_reference')->label('Client Reference')->nullable(),
            Select::make('country_id')->relationship('country', 'name')->label('Country')->preload()->searchable()->nullable()->live(),
            Select::make('city_id')->label('City')->searchable()->nullable()->options(fn ($get) => \App\Models\City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive(),
            Select::make('provider_branch_id')->label('Provider Branch')->searchable()->nullable()->options(fn ($get) => \App\Models\ProviderBranch::when($get('service_type_id') != 2, function ($query) use ($get) {
                return $query->where('city_id', $get('city_id'));
            })->where('service_types', 'like', '%' . \App\Models\ServiceType::find($get('service_type_id'))?->name . '%')->orderBy('priority', 'asc')->pluck('branch_name', 'id'))->reactive(),
            DatePicker::make('service_date')->label('Service Date')->nullable(),
            TimePicker::make('service_time')->label('Service Time')->nullable(),
            TextInput::make('address')->label('Address')->nullable(),
            Textarea::make('symptoms')->label('Symptoms')->nullable(),
            Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
        ]);
    }


    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['tasks as undone_tasks_count' => function ($query) {
                $query->where('is_done', false);
            }]))
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')->sortable()->searchable(),
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
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('serviceType.name')->label('Service Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch')->sortable()->searchable(),
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
                Tables\Actions\Action::make('View')->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id]))->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('Edit')->url(fn (File $record) => FileResource::getUrl('edit', ['record' => $record->id]))->icon('heroicon-o-pencil'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

}
