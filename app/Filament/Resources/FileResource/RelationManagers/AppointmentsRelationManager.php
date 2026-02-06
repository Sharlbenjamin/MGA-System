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
use App\Models\ProviderBranch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimized: eager loading (providerBranch, file), explicit select, pagination 10.
 * Create modal provider branch select uses searchable + preload(false) with getSearchResultsUsing
 * (same filtered branches as fileBranches()) to avoid loading all branches on mount.
 */
class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'Appointments';

    public function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('file_id')->default(fn() => $this->ownerRecord->getKey()),
            Select::make('provider_branch_id')->relationship('providerBranch', 'branch_name')->searchable()->preload(false)->required(),

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
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['providerBranch', 'file'])
                    ->select(['id', 'file_id', 'provider_branch_id', 'service_date', 'service_time', 'status']);
                return $query;
            })
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

                        Select::make('provider_branch_id')
                            ->label('Provider Branch')
                            ->searchable()
                            ->preload(false)
                            ->getSearchResultsUsing(function (string $search) {
                                $file = $this->ownerRecord;
                                $q = ProviderBranch::query()
                                    ->where('status', 'Active')
                                    ->orderBy('priority', 'asc');
                                if ($file->service_type_id) {
                                    $q->whereHas('services', fn ($sq) => $sq->where('service_type_id', $file->service_type_id));
                                }
                                if ($file->city_id) {
                                    $q->where('city_id', $file->city_id)->where('province_id', $file->city?->province_id);
                                }
                                if ($search !== '') {
                                    $q->where('branch_name', 'like', '%' . $search . '%');
                                }
                                return $q->limit(50)->pluck('branch_name', 'id');
                            })
                            ->getOptionLabelUsing(fn ($value) => ProviderBranch::find($value)?->branch_name ?? '')
                            ->required(),

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
