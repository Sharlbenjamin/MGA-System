<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Prescription;

class PrescriptionRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions'; // Make sure your File model defines prescriptions() relationship
    protected static ?string $title = 'Prescriptions';

    // Enable creation, editing, and deletion
    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('serial'),
                TextColumn::make('date')->date(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Add Prescription')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add Prescription')
                    ->modalButton('Create')
                    ->form([
                        Hidden::make('file_id')->default(fn() => $this->ownerRecord->getKey()),
                        TextInput::make('name')->required(),
                        Hidden::make('serial')->default(fn() => $this->ownerRecord->mga_reference . '-' . ($this->ownerRecord->prescriptions()->count() + 1)),
                        DatePicker::make('date')->default(now())->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->ownerRecord->prescriptions()->create($data);
                    }),
            ])
            ->actions([
                Action::make('viewDocument')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => $record->getDocumentSignedUrl())
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Action::make('downloadDocument')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->getDocumentSignedUrl())
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Edit Prescription')
                    ->modalButton('Update')
                    ->form(function ($record): array {
                        return [
                            // Keep file_id hidden and unchangeable
                            \Filament\Forms\Components\Hidden::make('file_id')
                    ->default($record->file_id),
                // Allow editing the name
                \Filament\Forms\Components\TextInput::make('name')
                    ->default($record->name)
                    ->required(),
                // Use a hidden field for serial since it is computed on create
                \Filament\Forms\Components\Hidden::make('serial')
                    ->default($record->serial),
                // Edit the date field
                \Filament\Forms\Components\DatePicker::make('date')
                    ->default($record->date)
                    ->required(),
                        ];
                    })
                    ->action(function ($record, array $data): void {
                        $record->update($data);

                    }),
                    \Filament\Tables\Actions\Action::make('deleteCustom')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation() // Prompts confirmation before deleting
                    ->action(function ($record): void {
                        $record->delete();
                    }),
                    Action::make('View')
                    ->label('View PRX')->color('info')->icon('heroicon-o-eye')
                    ->url(fn ($record) => \App\Filament\Resources\PrescriptionResource::getUrl('view', ['record' => $record->getKey()])),
                    Action::make('Export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')->color('success')
                    ->action(fn (Prescription $record) => response()->streamDownload(
                        fn () => print(Pdf::loadView('pdf.prescription', ['prescription' => $record])->output()),
                        $record->file->patient->name . ' Prescription Report ' . $record->file->mga_reference . '.pdf'
                    )),
                ])

            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}