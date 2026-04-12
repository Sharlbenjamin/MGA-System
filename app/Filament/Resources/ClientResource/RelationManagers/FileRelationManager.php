<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\FileResource;
use App\Models\File;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FileRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $model = File::class;

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')
                    ->label('MGA Reference')
                    ->sortable()
                    ->searchable()
                    ->url(fn (File $record): string => FileResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->sortable()
                    ->searchable(),
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
                        'Waiting MR' => 'primary',
                        'Refund' => 'primary',
                        'Cancelled' => 'danger',
                        'Void' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Waiting MR' => 'Waiting MR',
                        'Refund' => 'Refund',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                    ]),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(\App\Models\Country::pluck('name', 'id')),
                SelectFilter::make('service_type_id')
                    ->label('Service Type')
                    ->options(\App\Models\ServiceType::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                    ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('Edit')
                    ->url(fn (File $record) => FileResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-pencil'),
            ]);
    }
}
