<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssistedFileChecklistResource\Pages;
use App\Filament\Support\FileWorkflowActions;
use App\Filament\Support\FileWorkflowGapFilters;
use App\Models\File;
use App\Services\FileWorkflowGapService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssistedFileChecklistResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'File Checklist';

    protected static ?string $modelLabel = 'File checklist item';

    protected static ?string $pluralModelLabel = 'File Checklist';

    protected static ?string $slug = 'assisted-file-checklist';

    public static function getNavigationBadge(): ?string
    {
        return (string) FileWorkflowGapService::scopeAssistedChecklistBase(File::query())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'patient.client',
                'country',
                'city',
                'serviceType',
                'providerBranch.provider',
                'gops',
                'bills',
                'medicalReports',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => FileWorkflowGapService::scopeAssistedChecklistBase($query))
            ->columns([
                Tables\Columns\IconColumn::make('gap_gop')
                    ->label('GOP')
                    ->boolean()
                    ->getStateUsing(fn (File $record): bool => ! FileWorkflowGapService::missingGop($record))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\IconColumn::make('gap_gop_doc')
                    ->label('GOP Doc')
                    ->boolean()
                    ->getStateUsing(fn (File $record): bool => ! FileWorkflowGapService::missingGopDoc($record))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\IconColumn::make('gap_mr')
                    ->label('MR')
                    ->boolean()
                    ->getStateUsing(fn (File $record): bool => ! FileWorkflowGapService::missingMr($record))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\IconColumn::make('gap_bill')
                    ->label('Bill')
                    ->boolean()
                    ->getStateUsing(fn (File $record): bool => ! FileWorkflowGapService::missingBill($record))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('mga_reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_reference')
                    ->label('Client Reference')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('providerBranch.provider.name')
                    ->label('Provider')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Branch')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters(FileWorkflowGapFilters::forAssistedChecklist())
            ->actions([
                FileWorkflowActions::viewFile(),
                FileWorkflowActions::uploadGop(),
                FileWorkflowActions::uploadGopDoc(),
                FileWorkflowActions::uploadBill(),
            ])
            ->defaultSort('service_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssistedFileChecklist::route('/'),
        ];
    }
}
