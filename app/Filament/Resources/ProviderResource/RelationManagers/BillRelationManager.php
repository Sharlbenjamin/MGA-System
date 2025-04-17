<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Country;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    protected static ?string $title = 'Bills';

    protected static ?string $recordTitleAttribute = 'number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('total_amount')->money('eur'),
                Tables\Columns\TextColumn::make('paid_amount')->money('eur'),
                Tables\Columns\TextColumn::make('remaining_amount')->state(function ($record) {return $record->total_amount - $record->paid_amount;})->money('eur'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('bill_date')->date('d/m/Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'Paid' => 'Paid',
                    'Unpaid' => 'Unpaid',
                ]),
            ])
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
