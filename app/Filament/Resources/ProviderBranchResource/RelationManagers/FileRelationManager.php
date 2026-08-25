<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Filament\Resources\FileResource;
use App\Filament\Support\ContactProviderCommunications;
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
                    ->badge(),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
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
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                    ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ContactProviderCommunications::makeMissingDocumentsBulkAction(
                        fn () => $this->getOwnerRecord()->provider,
                        fn () => $this->getOwnerRecord(),
                    ),
                    ContactProviderCommunications::makeMissingBillsBulkAction(
                        fn () => $this->getOwnerRecord()->provider,
                        fn () => $this->getOwnerRecord(),
                    ),
                ]),
            ]);
    }
}
