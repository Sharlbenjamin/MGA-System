<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Filament\Resources\AppointmentResource\RelationManagers;
use App\Models\Appointment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Columns\TextColumn;

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
            Select::make('file_id')->relationship('file', 'id')->searchable()->required(),
            Select::make('provider_branch_id')->relationship('providerBranch', 'branch_name')->searchable()->required(),
            DatePicker::make('service_date')->required(),
            TimePicker::make('service_time')->nullable(),
            Select::make('status')
                ->options([
                    'Requested' => 'Requested',
                    'Pending' => 'Pending',
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
            TextColumn::make('file.id')->label('File ID')->sortable(),
            TextColumn::make('providerBranch.branch_name')->label('Provider Branch'),
            TextColumn::make('service_date')->label('Service Date')->date(),
            TextColumn::make('service_time')->label('Service Time'),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('created_at')->label('Created')->dateTime(),
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
