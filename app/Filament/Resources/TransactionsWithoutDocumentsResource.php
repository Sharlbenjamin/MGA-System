<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionsWithoutDocumentsResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsWithoutDocumentsResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Transaction without documents';
    protected static ?string $modelLabel = 'Transaction without documents';
    protected static ?string $pluralModelLabel = 'Transactions without documents';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('attachment_path')->orWhere('attachment_path', '')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('bank_account_id')
                    ->relationship('bankAccount', 'beneficiary_name')
                    ->required(),
                Forms\Components\Select::make('related_type')
                    ->options([
                        'Client' => 'Client',
                        'Patient' => 'Patient',
                        'Provider' => 'Provider',
                        'Branch' => 'Branch',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'Income' => 'Income',
                        'Outflow' => 'Outflow',
                        'Expense' => 'Expense',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('attachment_path')
                    ->label('Link or Text')
                    ->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('attachment_path')->orWhere('attachment_path', ''))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')
                    ->label('Bank Account')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('related_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Client' => 'success',
                        'Patient' => 'info',
                        'Provider' => 'warning',
                        'Branch' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Income' => 'success',
                        'Outflow' => 'danger',
                        'Expense' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'Income' => 'Income',
                        'Outflow' => 'Outflow',
                        'Expense' => 'Expense',
                    ]),
                Tables\Filters\SelectFilter::make('related_type')
                    ->options([
                        'Client' => 'Client',
                        'Patient' => 'Patient',
                        'Provider' => 'Provider',
                        'Branch' => 'Branch',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_transaction')
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record))
                    ->icon('heroicon-o-pencil')
                    ->label('Edit Transaction')
                    ->color('primary'),
                Tables\Actions\Action::make('add_document')
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record))
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->label('Add Document')
                    ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionsWithoutDocuments::route('/'),
        ];
    }
} 