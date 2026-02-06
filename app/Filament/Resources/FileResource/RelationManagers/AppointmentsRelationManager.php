<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use App\Services\DistanceCalculationService;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'Appointments';

    public function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('file_id')->default(fn() => $this->ownerRecord->getKey()),
            Select::make('provider_branch_id')->relationship('providerBranch', 'name')->searchable()->required(),

            DatePicker::make('service_date')->required(),
            TimePicker::make('service_time')->nullable(),

            Select::make('status')
                ->options([
                    'Requested' => 'Requested',
                    'Available' => 'Available',
                    'Confirmed' => 'Confirmed',
                    'Cancelled' => 'Cancelled',
                    ])
                    ->default('Requested')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['providerBranch', 'file']))
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('providerBranch.branch_name')->label('Provider Branch'),
                TextColumn::make('service_date')->label('Service Date')->date(),
                TextColumn::make('service_time')->label('Service Time'),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state): string => match ($state) {'Confirmed' => 'success','Available' => 'info','Requested' => 'warning','Cancelled' => 'danger',}),
                TextColumn::make('distance')
                    ->label('Distance (Car)')
                    ->getStateUsing(function ($record) {
                        $distanceService = app(DistanceCalculationService::class);
                        $distanceData = $distanceService->calculateFileToBranchDistance($record->file);
                        return $distanceService->getFormattedDistance($distanceData);
                    })
                    ->description(fn ($record) => $record->file->address ? 'From: ' . $record->file->address : 'No file address')
                    ->alignCenter(),
            ])
            ->headerActions([
                Action::make('create_appointment')
                    ->label('New Appointment')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Schedule an Appointment')
                    ->modalButton('Create Appointment')
                    ->form([
                        Hidden::make('file_id')
                            ->default(fn() => $this->ownerRecord->getKey()),

                        Select::make('provider_branch_id')->label('Provider Branch')->searchable()->preload()->required()
                        ->options(fn ($get) => \App\Models\File::find($get('file_id'))?->fileBranches()
                        ->pluck('branch_name', 'id') ?? []),

                        DatePicker::make('service_date')->required(),
                        TimePicker::make('service_time')->nullable(),

                        Select::make('status')
                            ->options([
                                'Requested' => 'Requested',
                                'Available' => 'Available',
                                'Confirmed' => 'Confirmed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Requested')
                            ->required(),
                    ])
                    ->action(fn(array $data) => $this->ownerRecord->appointments()->create($data))
                    ->successNotificationTitle('Appointment Created Successfully!'),
            ])
            ->actions([
                Action::make('edit_appointment')
                ->icon('heroicon-o-pencil')
                ->modalHeading('Edit')
                ->modalButton('Save Changes')
                ->form(fn ($record) => [
                    Select::make('provider_branch_id')
                        ->relationship('providerBranch', 'branch_name')
                        ->searchable()
                        ->required()
                        ->default($record->provider_branch_id),
                    DatePicker::make('service_date')->required()->default($record->service_date),
                    TimePicker::make('service_time')->nullable()->default($record->service_time),
                    Select::make('status')
                        ->options([
                            'Requested' => 'Requested',
                            'Available' => 'Available',
                            'Confirmed' => 'Confirmed',
                            'Cancelled' => 'Cancelled',
                        ])->searchable()
                        ->default($record->status)
                        ->required(),
                ]) ->action(function (array $data, $record) {
                    $record->update($data);
                })
                    ->successNotificationTitle('Appointment Updated Successfully!'),

                DeleteAction::make()
                    ->successNotificationTitle('Appointment Deleted Successfully!'),
            ]);
    }
}
