<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GopWithoutDocsResource\Pages;
use App\Models\Gop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GopWithoutDocsResource extends Resource
{
    protected static ?string $model = Gop::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'GOP without docs';
    protected static ?string $modelLabel = 'GOP without docs';
    protected static ?string $pluralModelLabel = 'GOP without docs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('file_id')
                    ->relationship('file', 'mga_reference')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'In' => 'In',
                        'Out' => 'Out',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                        'Updated' => 'Updated',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('gop_google_drive_link')
                    ->label('Google Drive Link')
                    ->url()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('gop_google_drive_link')->orWhere('gop_google_drive_link', ''))
            ->columns([
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('File Reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'In' => 'success',
                        'Out' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Not Sent' => 'gray',
                        'Sent' => 'info',
                        'Updated' => 'warning',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'In' => 'In',
                        'Out' => 'Out',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                        'Updated' => 'Updated',
                        'Cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_file')
                    ->url(fn (Gop $record): string => route('filament.admin.resources.files.edit', $record->file))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Tables\Actions\Action::make('upload_doc')
                    ->url(fn (Gop $record): string => route('filament.admin.resources.files.edit', $record->file) . '#gops')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->label('Upload Doc')
                    ->color('success'),
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
            'index' => Pages\ListGopWithoutDocs::route('/'),
        ];
    }
} 