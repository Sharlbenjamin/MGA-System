<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\Transaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

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
                Tables\Columns\TextColumn::make('attachment_path')
                    ->label('Attachment')
                    ->state(fn (Transaction $record): string => $record->attachment_path ? 'View Attachment' : 'No Attachment')
                    ->url(function (Transaction $record): ?string {
                        if (! $record->attachment_path) {
                            return null;
                        }

                        if ($record->isUrl() || $record->isGoogleDriveAttachment()) {
                            return $record->attachment_path;
                        }

                        return $record->getDocumentSignedUrl();
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record->id)),
            ])
            ->bulkActions([]);
    }
}
