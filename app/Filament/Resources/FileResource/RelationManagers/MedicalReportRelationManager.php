<?php

namespace App\Filament\Resources\FileResource\RelationManagers;


use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\MedicalReportResource;
use Filament\Forms\Components\Grid;
use App\Models\MedicalReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Tables\Actions;

class MedicalReportRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalReports'; // Uses the method in File model
    protected static ?string $title = 'Medical Reports ';

    protected static bool $canCreate = true;
    protected static bool $canEdit = true;
    protected static bool $canDelete = true;
    
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('diagnosis'),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('createModal')
                    ->label('Add New MR')
                    ->modalHeading('Add New Medical Report for '.$this->ownerRecord->patient->name)
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\Hidden::make('file_id')
                        ->default(fn () => $this->ownerRecord->getKey())
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('date')
                            ->required(),
                        \Filament\Forms\Components\Select::make('status')
                            ->options([
                                'Waiting'  => 'Waiting',
                                'Received' => 'Received',
                                'Not Sent' => 'Not Sent',
                                'Sent'     => 'Sent',
                            ])
                            ->default('Waiting')
                            ->required(),
                            \Filament\Forms\Components\Textarea::make('complain')->label('Complain')->nullable(),
                            \Filament\Forms\Components\Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
                            \Filament\Forms\Components\Textarea::make('history')->label('History')->nullable(),
                            \Filament\Forms\Components\TextInput::make('temperature')->label('Temperature')->nullable(),
                            \Filament\Forms\Components\TextInput::make('blood_pressure')->label('Blood Pressure')->nullable(),
                            \Filament\Forms\Components\TextInput::make('pulse')->label('Pulse')->nullable(),
                            \Filament\Forms\Components\Textarea::make('examination')->label('Examination')->nullable(),
                            \Filament\Forms\Components\Textarea::make('advice')->label('Advice')->nullable(),
                    ])
                    ->action(function (array $data) {
                        // Create the new Medical Report record.
                        \App\Models\MedicalReport::create($data);
                    })
                    ->successNotificationTitle('Medical Report Created'),
            ])
            ->actions([
                Actions\Action::make('export')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function ($record) {
                    $medicalReport = $record;
                    $pdf = Pdf::loadView('pdf.medicalReport', ['medicalReport' => $medicalReport]);
                    $fileName = $medicalReport->file->patient->name . ' Medical Report ' . $medicalReport->file->mga_reference . '.pdf';
                    
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $fileName
                    );
                }),
                \Filament\Tables\Actions\Action::make('editMR')
                    ->label('Edit')
                    ->modalHeading('Edit Medical Report')
                    ->modalButton('Save')
                    ->form(function (\Illuminate\Database\Eloquent\Model $record): array {
                        return [
                            \Filament\Forms\Components\Hidden::make('file_id')
                                ->default($record->file_id),
                            \Filament\Forms\Components\DatePicker::make('date')
                                ->default($record->date)
                                ->required(),
                            \Filament\Forms\Components\Select::make('status')
                                ->default($record->status)
                                ->options([
                                    'Waiting'  => 'Waiting',
                                    'Received' => 'Received',
                                    'Not Sent' => 'Not Sent',
                                    'Sent'     => 'Sent',
                                ])
                                ->required(),
                                \Filament\Forms\Components\Textarea::make('complain')->label('Complain')->default($record->complain)->nullable(),
                                \Filament\Forms\Components\Textarea::make('diagnosis')->label('Diagnosis')->default($record->diagnosis)->nullable(),
                                \Filament\Forms\Components\Textarea::make('history')->label('History')->default($record->history)->nullable(),
                                \Filament\Forms\Components\TextInput::make('temperature')->label('Temperature')->default($record->temperature)->nullable(),
                                \Filament\Forms\Components\TextInput::make('blood_pressure')->label('Blood Pressure')->default($record->blood_pressure)->nullable(),
                                \Filament\Forms\Components\TextInput::make('pulse')->label('Pulse')->default($record->pulse)->nullable(),
                                \Filament\Forms\Components\Textarea::make('examination')->label('Examination')->default($record->examination)->nullable(),
                                \Filament\Forms\Components\Textarea::make('advice')->label('Advice')->default($record->advice)->nullable(),
                        ];
                    })
                    ->action(function (\Illuminate\Database\Eloquent\Model $record, array $data): void {
                        // Update the record with the new data.
                        $record->update($data);
                    }),
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
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}