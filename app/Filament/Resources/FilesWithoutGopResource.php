<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FilesWithoutGopResource\Pages;
use App\Models\File;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FilesWithoutGopResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Files without GOP';
    protected static ?string $modelLabel = 'File without GOP';
    protected static ?string $pluralModelLabel = 'Files without GOP';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDoesntHave('gops')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('mga_reference')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('patient_id')
                    ->relationship('patient', 'name')
                    ->required(),
                Forms\Components\Select::make('country_id')
                    ->relationship('country', 'name')
                    ->required(),
                Forms\Components\Select::make('city_id')
                    ->relationship('city', 'name')
                    ->required(),
                Forms\Components\Select::make('service_type_id')
                    ->relationship('serviceType', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('service_date')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('gops'))
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'New' => 'gray',
                        'Handling' => 'warning',
                        'Available' => 'info',
                        'Confirmed' => 'primary',
                        'Assisted' => 'success',
                        'Hold' => 'danger',
                        'Cancelled' => 'danger',
                        'Void' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                    ]),
                Tables\Filters\SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country'),
                Tables\Filters\SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('City'),
                Tables\Filters\SelectFilter::make('service_type_id')
                    ->relationship('serviceType', 'name')
                    ->label('Service Type'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (File $record): string => route('filament.admin.resources.files.edit', $record))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Tables\Actions\Action::make('create_gop')
                    ->url(fn (File $record): string => route('filament.admin.resources.files.edit', $record) . '#gops')
                    ->icon('heroicon-o-plus')
                    ->label('Create GOP')
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
            'index' => Pages\ListFilesWithoutGops::route('/'),
        ];
    }
} 