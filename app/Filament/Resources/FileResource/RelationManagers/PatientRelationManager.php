<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class PatientRelationManager extends RelationManager
{
    protected static string $relationship = 'patient'; // Make sure this matches your File model relationship name
    protected static ?string $title = 'Patient';

    // Enable create, edit and delete operations
    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client']))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('client.company_name'),
                TextColumn::make('date')->date(),
            ])
            ->headerActions([
                // Create via modal action
                Action::make('create')
                    ->label('Add')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add GOP')
                    ->modalButton('Create')
                    ->form([
                        // Use a hidden field to set file_id from the owner record
                        Hidden::make('file_id')
                            ->default(fn() => $this->ownerRecord->getKey()),
                        Select::make('type')
                            ->options([
                                'In'  => 'In',
                                'Out' => 'Out',
                            ])
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        DatePicker::make('date')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        // Create the GOP record using the parent model's relation
                        $this->ownerRecord->gops()->create($data);
                    })
            ])
            ->actions([
                // Edit via modal action
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Edit GOP')
                    ->modalButton('Update')
                    ->form(function ($record) {
                        return [
                            // file_id can be hidden and unchanged
                            Hidden::make('file_id')
                                ->default($record->file_id),
                            Select::make('type')
                                ->options([
                                    'In'  => 'In',
                                    'Out' => 'Out',
                                ])
                                ->default($record->type)
                                ->required(),
                            TextInput::make('amount')
                                ->numeric()
                                ->default($record->amount)
                                ->required(),
                            DatePicker::make('date')
                                ->default($record->date)
                                ->required(),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $record->update($data);
                    }),
                // Delete action
                \Filament\Tables\Actions\Action::make('deleteCustom')
                    ->label('Delete')
                    ->icon('heroicon-o-trash') // You can use any icon supported by Filament
                    ->color('danger')
                    ->requiresConfirmation() // Prompts a confirmation modal before deleting
                    ->action(function ($record) {
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
