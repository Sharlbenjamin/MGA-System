<?php

namespace App\Filament\Resources\PrescriptionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class DrugRelationManager extends RelationManager
{
    // This must match the relationship method name defined in your Prescription model.
    protected static string $relationship = 'drugs';

    protected static ?string $title = 'Drugs';

    // Enable create, edit and delete actions.
    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('pharmaceutical')->label('Pharmaceutical'),
                TextColumn::make('dose')->label('Dose'),
                TextColumn::make('duration')->label('Duration'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Add Drug')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add Drug')
                    ->modalButton('Create')
                    ->form([
                        // Automatically assign the prescription_id from the owner record.
                        Hidden::make('prescription_id')
                            ->default(fn() => $this->ownerRecord->getKey()),
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('pharmaceutical')
                            ->required(),
                        TextInput::make('dose')
                            ->required(),
                        TextInput::make('duration')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        // Create the drug using the parent prescription's relationship.
                        $this->ownerRecord->drugs()->create($data);
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Edit Drug')
                    ->modalButton('Update')
                    ->form(function ($record): array {
                        return [
                            Hidden::make('prescription_id')
                                ->default($record->prescription_id),
                            TextInput::make('name')
                                ->default($record->name)
                                ->required(),
                            TextInput::make('pharmaceutical')
                                ->default($record->pharmaceutical)
                                ->required(),
                            TextInput::make('dose')
                                ->default($record->dose)
                                ->required(),
                            TextInput::make('duration')
                                ->default($record->duration)
                                ->required(),
                        ];
                    })
                    ->action(function ($record, array $data): void {
                        $record->update($data);
                    }),
                    Action::make('deleteCustom')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation() // Prompts the user to confirm deletion
                    ->action(function ($record): void {
                        $record->delete();
                    }),

            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}