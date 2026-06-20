<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionsOutWithoutBillsResource\Pages;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionDocumentationForm;
use App\Models\Transaction;
use App\Services\TransactionDocumentationStatsService;
use App\Services\TransactionIntegrityService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsOutWithoutBillsResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Transactions Out without bills';

    protected static ?string $modelLabel = 'Transaction out without bills';

    protected static ?string $pluralModelLabel = 'Transactions Out without bills';

    protected static ?string $slug = 'transactions-out-without-bills';

    public static function getNavigationBadge(): ?string
    {
        return (string) TransactionIntegrityService::scopeOutflowWithoutBills(Transaction::query())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bankAccount', 'bills']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => TransactionIntegrityService::scopeOutflowWithoutBills($query))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->label('Bank account'),
                Tables\Columns\TextColumn::make('reference')->limit(30)->toggleable(),
                Tables\Columns\TextColumn::make('name')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Comment')
                    ->limit(25)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('related_party')
                    ->label('Provider / branch')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->getRelatedPartyLabel())
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('documentation_category')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state, Transaction $record): string => TransactionDocumentationStatsService::categoryLabel(
                        TransactionDocumentationStatsService::resolveCategoryKey($record)
                    )),
                Tables\Columns\TextColumn::make('documentation_status')
                    ->label('Documentation')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success',
                        'unlinked' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_transaction')
                    ->label('Edit transaction')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('view_bank_transactions')
                    ->label('View bank transactions')
                    ->icon('heroicon-o-building-library')
                    ->visible(fn (Transaction $record): bool => filled($record->bank_account_id))
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('index', ['bankAccount' => $record->bank_account_id])),
                TransactionDocumentationForm::makeTableAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionsOutWithoutBills::route('/'),
        ];
    }
}
