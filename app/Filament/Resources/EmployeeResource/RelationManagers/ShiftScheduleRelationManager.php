<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Shift;
use App\Models\ShiftSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftScheduleRelationManager extends RelationManager
{
    protected static string $relationship = 'shiftSchedules';

    protected static ?string $title = 'Schedule';

    protected static ?string $recordTitleAttribute = 'scheduled_date';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('scheduled_date')
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('shift_id')
                    ->label('Shift')
                    ->options(Shift::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('location_type')
                    ->options(ShiftSchedule::locationTypes())
                    ->default('on_site'),
                Forms\Components\Textarea::make('notes')->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('scheduled_date')
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('shift.name')->sortable(),
                Tables\Columns\TextColumn::make('shift.time_range')
                    ->getStateUsing(fn (ShiftSchedule $record) => $record->shift->time_range)
                    ->label('Time'),
                Tables\Columns\TextColumn::make('location_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ShiftSchedule::locationTypes()[$state] ?? $state),
                Tables\Columns\TextColumn::make('notes')->limit(30),
            ])
            ->defaultSort('scheduled_date', 'desc')
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
