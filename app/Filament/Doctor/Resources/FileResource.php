<?php

namespace App\Filament\Doctor\Resources;

use App\Filament\Doctor\Resources\FileResource\Pages;
use App\Filament\Doctor\Resources\FileResource\RelationManagers;
use App\Models\File;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('mga_reference')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('patient.name')->label('Patient')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('city.name')->label('City')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('serviceType.name')->label('Service Type')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('service_date')->date()->sortable(),
        ])
        ->filters([
            TernaryFilter::make('user_provider_only')
                ->label('Show Only My Files')
                ->trueLabel('Yes')
                ->falseLabel('No')
                ->query(fn (Builder $query, $state) => 
                    $state ? $query->whereHas('providerBranch.provider', fn ($q) => 
                        $q->where('name', auth()->user()->name)
                    ) : $query
                ),
        ])
            ->actions([
                Tables\Actions\Action::make('View')
                ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id])) 
                ->icon('heroicon-o-eye')
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
            RelationManagers\MedicalReportRelationManager::class, // Registers the Medical Reports table
            RelationManagers\PrescriptionRelationManager::class, // Registers the Medical Reports table
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiles::route('/'),
            'create' => Pages\CreateFile::route('/create'),
            'edit' => Pages\EditFile::route('/{record}/edit'),
            'view' => Pages\ViewFile::route('/{record}/show'),
        ];
    }
}
