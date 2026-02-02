<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryRelationManager extends RelationManager
{
    protected static string $relationship = 'salaries';

    protected static ?string $title = 'Salaries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('year')
                    ->options(fn () => collect(range(now()->year - 2, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y]))
                    ->required(),
                Forms\Components\Select::make('month')
                    ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => now()->month($m)->format('F')]))
                    ->required(),
                Forms\Components\TextInput::make('base_salary')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\TextInput::make('adjustments')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('deductions')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('net_salary')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\Toggle::make('is_locked'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\TextColumn::make('month')
                    ->formatStateUsing(fn (int $state): string => now()->month($state)->format('F'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_salary')->money()->sortable(),
                Tables\Columns\TextColumn::make('adjustments')->money(),
                Tables\Columns\TextColumn::make('deductions')->money(),
                Tables\Columns\TextColumn::make('net_salary')->money()->sortable(),
                Tables\Columns\IconColumn::make('is_locked')
                    ->boolean(),
            ])
            ->defaultSort('year', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->orderBy('year', 'desc')->orderBy('month', 'desc'))
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
