<?php

namespace App\Filament\Resources\BillResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€'),

                Forms\Components\TextInput::make('discount')
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€')->default('0'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('discount')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('tax')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('total')
                    ->money('EUR'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->after(function () {
                        $this->getOwnerRecord()->calculateTotal();
                    }),
            ]);
    }
}
