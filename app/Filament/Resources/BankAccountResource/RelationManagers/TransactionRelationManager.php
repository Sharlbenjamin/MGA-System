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
                Tables\Columns\TextColumn::make('amount')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('type')->searchable()->sortable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\TextColumn::make('attachment_path')->searchable(),
                Tables\Columns\TextColumn::make('bank_charges')->money()->sortable(),

            ])
            ->groups([
                Tables\Grouping\Group::make('date')
                    ->label('Month')
                    ->date()
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Transaction $record) => $record->date->format('F Y'))
                    ->getDescriptionFromRecordUsing(fn (Transaction $record) => $record->date->format('F Y') . ' Balance: ' . $record->bankAccount->monthlyBalance($record->date)),
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
