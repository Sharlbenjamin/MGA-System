<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FilesWithoutMRResource\Pages;
use App\Models\File;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class FilesWithoutMRResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Workflow';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Files without MR';
    protected static ?string $modelLabel = 'File without MR';
    protected static ?string $pluralModelLabel = 'Files without MR';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Assisted')
            ->whereDoesntHave('medicalReports')
            ->count();
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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Assisted')
                ->whereDoesntHave('medicalReports')
                ->with(['patient', 'providerBranch', 'serviceType']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Case Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('mga_reference')
                    ->label('MGA Reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Provider Branch')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        if (!$record->providerBranch) {
                            return 'No Provider Branch Assigned';
                        }
                        return $record->providerBranch->branch_name ?? 'N/A';
                    }),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->searchable()
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
                        'Waiting MR' => 'primary',
                        'Refund' => 'primary',
                        'Cancelled' => 'danger',
                        'Void' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider_branch_id')
                    ->relationship('providerBranch', 'branch_name')
                    ->label('Provider Branch')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('service_type_id')
                    ->relationship('serviceType', 'name')
                    ->label('Service Type'),
                Tables\Filters\SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country'),
                Tables\Filters\SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('City'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (File $record): string => route('filament.admin.resources.files.edit', $record))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
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
            'index' => Pages\ListFilesWithoutMR::route('/'),
        ];
    }
}
