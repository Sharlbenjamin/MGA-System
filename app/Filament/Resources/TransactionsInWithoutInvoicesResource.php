<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionsInWithoutInvoicesResource\Pages;
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

class TransactionsInWithoutInvoicesResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Transactions In without invoices';

    protected static ?string $modelLabel = 'Transaction in without invoices';

    protected static ?string $pluralModelLabel = 'Transactions In without invoices';

    protected static ?string $slug = 'transactions-in-without-invoices';

    public static function getNavigationBadge(): ?string
    {
        return (string) TransactionIntegrityService::scopeIncomeLinkIssues(Transaction::query())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bankAccount', 'invoices']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => TransactionIntegrityService::scopeIncomeLinkIssues($query))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (net)')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bank_charges')
                    ->label('Bank charges')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoices_total')
                    ->label('Invoices total')
                    ->getStateUsing(fn (Transaction $record): string => $record->invoices()->exists()
                        ? '€'.number_format(TransactionIntegrityService::invoicesTotalFor($record), 2)
                        : '—'),
                Tables\Columns\TextColumn::make('difference')
                    ->label('Difference')
                    ->getStateUsing(function (Transaction $record): string {
                        if (! $record->invoices()->exists()) {
                            return '—';
                        }

                        $diff = TransactionIntegrityService::invoiceAmountDifferenceFor($record);

                        return '€'.number_format($diff, 2);
                    })
                    ->color(fn (Transaction $record): string => TransactionIntegrityService::hasInvoiceTotalMismatch($record) ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('linking_status')
                    ->label('Linking status')
                    ->badge()
                    ->getStateUsing(fn (Transaction $record): string => TransactionIntegrityService::linkingIssueLabel($record))
                    ->color(fn (Transaction $record): string => match (TransactionIntegrityService::linkingIssueLabel($record)) {
                        'OK' => 'success',
                        'Amount mismatch' => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (Transaction $record): ?string => TransactionIntegrityService::invoiceTotalMismatchTooltip($record)),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->label('Bank account'),
                Tables\Columns\TextColumn::make('related_party')
                    ->label('Client')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->getRelatedPartyLabel())
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Invoices')
                    ->counts('invoices'),
                Tables\Columns\TextColumn::make('documentation_category')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state, Transaction $record): string => TransactionDocumentationStatsService::categoryLabel(
                        TransactionDocumentationStatsService::resolveCategoryKey($record)
                    )),
                Tables\Columns\TextColumn::make('documentation_status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('linking_issue')
                    ->label('Issue type')
                    ->options([
                        'all' => 'All issues',
                        'unlinked' => 'Unlinked only',
                        'amount_mismatch' => 'Amount mismatch only',
                    ])
                    ->default('all')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? 'all';

                        return match ($value) {
                            'unlinked' => TransactionIntegrityService::scopeIncomeUnlinkedOnly($query),
                            'amount_mismatch' => TransactionIntegrityService::applyInvoiceTotalMismatchScope($query),
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
            'index' => Pages\ListTransactionsInWithoutInvoices::route('/'),
        ];
    }
}
