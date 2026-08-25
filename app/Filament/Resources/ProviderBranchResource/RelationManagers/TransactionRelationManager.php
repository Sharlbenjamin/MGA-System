<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Filament\Support\ContactProviderCommunications;
use App\Models\Transaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionRelationManager extends RelationManager
{
    protected static string $relationship = 'branchTransactions';

    protected static ?string $title = 'Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('date')->orderByDesc('id'))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Transaction Date')
                    ->date('d-m-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record->id)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ContactProviderCommunications::makeTransactionBulkAction(
                        fn () => $this->getOwnerRecord()->provider,
                        fn () => $this->getOwnerRecord(),
                    ),
                ]),
            ]);
    }
}
