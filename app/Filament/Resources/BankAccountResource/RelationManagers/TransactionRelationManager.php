<?php

namespace App\Filament\Resources\BankAccountResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class TransactionRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('related_type')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('related_id')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable()
                    ->summarize([
                        Summarizer::make('trx_in')
                            ->label('Trx In')
                            ->money('EUR')
                            ->using(fn ($query): float => (float) (clone $query)->where('type', 'Income')->sum('amount')),
                        Summarizer::make('trx_out')
                            ->label('Trx Out')
                            ->money('EUR')
                            ->using(fn ($query): float => (float) (clone $query)->whereIn('type', ['Outflow', 'Expense'])->sum('amount')),
                        Summarizer::make('net')
                            ->label('Net')
                            ->money('EUR')
                            ->using(function ($query): float {
                                $income = (float) (clone $query)->where('type', 'Income')->sum('amount');
                                $outflow = (float) (clone $query)->whereIn('type', ['Outflow', 'Expense'])->sum('amount');

                                return $income - $outflow;
                            }),
                    ]),
                Tables\Columns\TextColumn::make('type')->searchable()->sortable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\TextColumn::make('attachment_path')->searchable(),
                Tables\Columns\TextColumn::make('bank_charges')->money()->sortable(),

            ])
            ->groups([
                Tables\Grouping\Group::make('date')
                    ->label('Month')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (Transaction $record): string => $record->date?->format('Y-m') ?? 'unknown')
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->date?->format('F Y') ?? 'Unknown')
                    ->getDescriptionFromRecordUsing(function (Transaction $record): string {
                        if (! auth()->user()?->isAdmin()) {
                            return '';
                        }

                        return 'Balance: '.$record->bankAccount->monthlyBalance($record->date);
                    })
                    ->scopeQueryUsing(function (Builder $query, $record): Builder {
                        if (! $record instanceof Transaction || $record->date === null) {
                            return $query->whereNull('transactions.date');
                        }

                        return $query
                            ->whereYear('transactions.date', $record->date->year)
                            ->whereMonth('transactions.date', $record->date->month);
                    }),
            ])
            ->defaultGroup('date')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('viewAll')
                    ->label('Open full list')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (): string => TransactionResource::indexUrlFor($this->getOwnerRecord())),
                Tables\Actions\CreateAction::make()
                    ->label('New transaction')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(fn (): string => TransactionResource::getUrl('create', [
                        'bank_account_id' => $this->getOwnerRecord()->getKey(),
                    ])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->url(fn ($record) => route('filament.admin.resources.transactions.edit', $record->id)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
