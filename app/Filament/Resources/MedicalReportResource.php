<?php

namespace App\Filament\Resources;

use Illuminate\Support\Facades\Auth;

use App\Filament\Resources\MedicalReportResource\Pages;
use App\Filament\Resources\MedicalReportResource\RelationManagers;
use App\Models\MedicalReport;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\BelongsToSelect;
use Filament\Facades\Filament;


class MedicalReportResource extends Resource
{
    protected static ?string $model = MedicalReport::class;

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('file_id')
                    ->relationship('file', 'mga_reference')
                    ->label('File')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\DatePicker::make('date')
                    ->label('Date')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Waiting' => 'Waiting',
                        'Received' => 'Received',
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                    ])
                    ->default('Waiting')
                    ->required(),
                Forms\Components\Textarea::make('complain')
                    ->label('Complain')
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('diagnosis')
                    ->label('Diagnosis')
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('history')
                    ->label('History')
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('temperature')
                    ->label('Temperature')
                    ->nullable(),
                Forms\Components\TextInput::make('blood_pressure')
                    ->label('Blood Pressure')
                    ->nullable(),
                Forms\Components\TextInput::make('pulse')
                    ->label('Pulse')
                    ->nullable(),
                Forms\Components\Textarea::make('examination')
                    ->label('Examination')
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('advice')
                    ->label('Advice')
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('document_path')
                    ->label('Medical Report Document')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240) // 10MB
                    ->nullable()
                    ->disk('public')
                    ->directory('medical-reports')
                    ->visibility('public')
                    ->helperText('Upload the medical report document (PDF or image)')
                    ->storeFileNamesIn('original_filename')
                    ->downloadable()
                    ->openable()
                    ->preserveFilenames()
                    ->maxFiles(1)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('MGA Reference')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file.patient.name')
                    ->label('Patient')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('document_path')
                    ->label('Document')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record) => !empty($record->document_path)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Waiting' => 'Waiting',
                        'Received' => 'Received',
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_document')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Tables\Actions\Action::make('download_document')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->document_path ? asset('storage/' . $record->document_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedicalReports::route('/'),
            'create' => Pages\CreateMedicalReport::route('/create'),
            'edit' => Pages\EditMedicalReport::route('/{record}/edit'),
        ];
    }


}
