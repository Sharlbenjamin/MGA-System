<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Services\DistanceCalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('file_id')->relationship('file', 'id')->searchable()->required(),
            Forms\Components\Select::make('provider_branch_id')->relationship('providerBranch', 'branch_name')->searchable()->required(),
            Forms\Components\DatePicker::make('service_date')->required(),
            Forms\Components\TimePicker::make('service_time')->nullable(),
            Forms\Components\Select::make('status')
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

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('file.id')->label('File ID')->sortable(),
            Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch'),
            Tables\Columns\TextColumn::make('service_date')->label('Service Date')->date(),
            Tables\Columns\TextColumn::make('service_time')->label('Service Time'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('distance')
                ->label('Distance (Car)')
                ->getStateUsing(function ($record) {
                    $distanceService = app(DistanceCalculationService::class);
                    $distanceData = $distanceService->calculateFileToBranchDistance($record->file);
                    return $distanceService->getFormattedDistance($distanceData);
                })
                ->description(fn ($record) => $record->file->address ? 'From: ' . $record->file->address : 'No file address')
                ->alignCenter(),
            Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime(),
        ]) ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
