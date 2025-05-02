<?php

namespace App\Filament\Resources\BankAccountResource\RelationManagers;

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
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->sortable(),
                Tables\Columns\TextColumn::make('related_type')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('related_id')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount')->numeric()->sortable()
                ->numeric()
                ->sortable()
                ->summarize([
                    Tables\Columns\Summarizers\Sum::make()
                        ->money('EUR')
                        ->label('Monthly Total')
                ]),
                Tables\Columns\TextColumn::make('type')->searchable()->sortable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('attachment_path')->searchable(),
                Tables\Columns\TextColumn::make('bank_charges')->money()->sortable(),

            ])
            ->groups([
                Tables\Grouping\Group::make('date')
                    ->label('Month')
                    ->date()
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->date->format('F Y'))
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('date', 'desc')),
            ])
            ->defaultGroup('date')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->url(fn () => route('filament.admin.resources.transactions.create')),
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
