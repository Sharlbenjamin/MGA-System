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

    protected static ?string $navigationLabel = 'Trx Out ≠ Bills';

    protected static ?string $modelLabel = 'Trx Out ≠ Bill';

    protected static ?string $pluralModelLabel = 'Trx Out ≠ Bills';

    protected static ?string $slug = 'transactions-out-without-bills';

    public static function getNavigationBadge(): ?string
    {
        return (string) TransactionIntegrityService::scopeOutflowLinkIssues(Transaction::query())->count();
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
            ->modifyQueryUsing(fn (Builder $query): Builder => TransactionIntegrityService::scopeOutflowLinkIssues($query))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('bills_total')
                    ->label('Linked bills paid')
                    ->getStateUsing(fn (Transaction $record): string => $record->bills()->exists()
                        ? '€'.number_format(TransactionIntegrityService::billsPaidTotalFor($record), 2)
                        : '—'),
                Tables\Columns\TextColumn::make('difference')
                    ->label('Difference')
                    ->getStateUsing(function (Transaction $record): string {
                        if (! $record->bills()->exists()) {
                            return '—';
                        }

                        $diff = TransactionIntegrityService::billAmountDifferenceFor($record);

                        return '€'.number_format($diff, 2);
                    })
                    ->color(fn (Transaction $record): string => TransactionIntegrityService::hasBillTotalMismatch($record) ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('linking_status')
                    ->label('Linking status')
                    ->badge()
                    ->getStateUsing(fn (Transaction $record): string => TransactionIntegrityService::outflowLinkingIssueLabel($record))
                    ->color(fn (Transaction $record): string => match (true) {
                        TransactionIntegrityService::outflowLinkingIssueLabel($record) === 'OK' => 'success',
                        str_contains(TransactionIntegrityService::outflowLinkingIssueLabel($record), 'Amount mismatch') => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (Transaction $record): ?string => TransactionIntegrityService::billTotalMismatchTooltip($record)),
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
                Tables\Columns\TextColumn::make('bills_count')
                    ->label('Bills')
                    ->counts('bills'),
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
            ->filters([
                Tables\Filters\SelectFilter::make('linking_issue')
                    ->label('Issue type')
                    ->options([
                        'all' => 'All issues',
                        'without_provider' => 'Without provider',
                        'without_bills' => 'Without bills',
                        'amount_mismatch' => 'Amount mismatch',
                    ])
                    ->default('all')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? 'all';

                        return match ($value) {
                            'without_provider' => TransactionIntegrityService::scopeOutflowWithoutProvider($query),
                            'without_bills' => TransactionIntegrityService::scopeOutflowWithoutBills($query),
                            'amount_mismatch' => TransactionIntegrityService::applyBillTotalMismatchScope($query),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->relationship('bankAccount', 'beneficiary_name')
                    ->label('Bank account')
                    ->searchable()
                    ->preload(),
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
