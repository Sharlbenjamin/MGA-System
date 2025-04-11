<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->options([
                    'Income' => 'Income',
                    'Outflow' => 'Outflow',
                    'Expense' => 'Expense',
                ])->required(),
                //Forms\Components\Select::make('transaction_group_id')->relationship('transactionGroup', 'id'),
                Forms\Components\Select::make('related_type')->options([
                    'Invoice' => 'Invoice',
                    'Bill' => 'Bill',
                    'Expense' => 'Expense',
                    ])->required()->reactive(),
                    // I want to select an invoice if realted_type is Client
                Forms\Components\Select::make('related_id')->label('Invoice')->required()->options(Invoice::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Client'),
                Forms\Components\Select::make('related_id')->label('Bill')->required()->options(Bill::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Provider'),
                Forms\Components\Select::make('bank_account_id')->relationship('bankAccount', 'beneficiary_name')->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('amount')->required()->numeric(),
                Forms\Components\DatePicker::make('date')->required()->default(now()),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\TextInput::make('attachment_path')->maxLength(255),
                Forms\Components\TextInput::make('bank_charges')
                    ->numeric()
                    ->prefix('$')
                    ->maxValue(999999.99)
                    ->default(0),

                Forms\Components\Toggle::make('charges_covered_by_client')
                    ->default(false),


                    // I want to have a table to select the related invoice or bill

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bankAccount.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('related_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('related_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bank_charges')
                    ->money()
                    ->sortable(),
                Tables\Columns\IconColumn::make('charges_covered_by_client')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
