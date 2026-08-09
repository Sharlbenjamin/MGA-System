<?php

namespace App\Filament\Resources\BillResource\RelationManagers;

use App\Filament\Resources\BillResource\Pages\EditBill;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionBillLinkForm;
use App\Models\Bill;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransactionRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === EditBill::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                $query->select(
                    'transactions.*',
                    'bill_transaction.amount_paid',
                );
            })
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn (Transaction $record): ?string => $record->name)
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record]))
                    ->color('primary'),
                TextColumn::make('amount')
                    ->label('Transaction amount')
                    ->money('EUR')
                    ->summarize(Sum::make()->money('EUR')),
                TextColumn::make('amount_paid')
                    ->label('Paid on this bill')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(fn ($query) => $query->selectRaw('SUM(bill_transaction.amount_paid)'))
                    ),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Income' => 'success',
                        'Outflow' => 'warning',
                        'Expense' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Payment status')
                    ->badge()
                    ->colors([
                        'Draft' => 'gray',
                        'Completed' => 'success',
                        'Pending' => 'warning',
                    ])
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('editPaidAmount')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit paid amount')
                    ->fillForm(fn (Transaction $record): array => [
                        'amount_paid' => (float) ($record->amount_paid ?? $record->pivot?->amount_paid ?? 0),
                    ])
                    ->form(fn (Transaction $record): array => [
                        Forms\Components\Placeholder::make('transaction_name')
                            ->label('Transaction')
                            ->content($record->name),
                        Forms\Components\Placeholder::make('transaction_amount')
                            ->label('Transaction amount')
                            ->content('€'.number_format((float) $record->amount, 2)),
                        Forms\Components\Placeholder::make('bill_total')
                            ->label('Bill total')
                            ->content(function (): string {
                                /** @var Bill $bill */
                                $bill = $this->getOwnerRecord();

                                return '€'.number_format((float) $bill->total_amount, 2);
                            }),
                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Paid amount on this transaction')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->prefix('€')
                            ->required(),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        TransactionBillLinkForm::updatePaidAmount(
                            $record,
                            $this->getOwnerRecord(),
                            (float) $data['amount_paid'],
                        );

                        $this->dispatch('$refresh');
                    }),
                Tables\Actions\Action::make('view')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('delete')
                    ->label('Unlink')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove transaction from bill')
                    ->modalDescription('This unlinks the transaction from this bill and recalculates its paid status.')
                    ->action(function (Transaction $record): void {
                        TransactionBillLinkForm::detachBill($record, $this->getOwnerRecord());

                        $this->dispatch('$refresh');
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No linked transactions')
            ->emptyStateDescription('Link this bill from a transaction to track payments here.');
    }
}
