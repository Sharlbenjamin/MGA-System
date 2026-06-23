<?php

namespace App\Filament\Resources\TransactionResource\RelationManager;

use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Support\TransactionBillLinkForm;
use App\Filament\Support\TransactionEditPageRefresh;
use App\Models\Bill;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BillRelationManager extends RelationManager
{
    protected static bool $isLazy = true;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'Outflow'
            && $pageClass === EditTransaction::class;
    }

    protected static string $relationship = 'bills';

    protected static ?string $title = 'Bills';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function (Builder $query): void {
                $query->select(
                    'bills.*',
                    'bill_transaction.amount_paid',
                )->selectSub(
                    DB::table('bill_transaction')
                        ->selectRaw('COALESCE(SUM(amount_paid), 0)')
                        ->whereColumn('bill_id', 'bills.id'),
                    'bill_total_paid',
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Bill')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Bill total')
                    ->money('EUR')
                    ->summarize(Sum::make()->money('EUR')),
                TextColumn::make('amount_paid')
                    ->label('Paid on this transaction')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(fn ($query) => $query->selectRaw('SUM(bill_transaction.amount_paid)'))
                    ),
                TextColumn::make('bill_remaining')
                    ->label('Bill remaining')
                    ->money('EUR')
                    ->getStateUsing(fn (Bill $record): float => max(
                        0,
                        round((float) $record->total_amount - (float) ($record->bill_total_paid ?? 0), 2),
                    )),
                TextColumn::make('status')
                    ->label('Status')
                    ->description(fn (Bill $record): string => $record->bill_date?->format('d/m/Y') ?? '—')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Unpaid' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addBill')
                    ->label('Add bill')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add bill')
                    ->modalSubmitActionLabel('Add')
                    ->form(fn (): array => TransactionBillLinkForm::attachFormSchema(
                        $this->ownerRecord,
                        $this->ownerRecord->bills()->pluck('bills.id')->all(),
                    ))
                    ->action(function (array $data): void {
                        TransactionBillLinkForm::attachBill(
                            $this->ownerRecord,
                            (int) $data['bill_id'],
                            (float) $data['amount_paid'],
                        );

                        TransactionEditPageRefresh::refresh($this);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('editPaidAmount')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit paid amount')
                    ->fillForm(fn (Bill $record): array => [
                        'amount_paid' => (float) ($record->amount_paid ?? 0),
                    ])
                    ->form(fn (Bill $record): array => TransactionBillLinkForm::editPaidAmountSchema($record))
                    ->action(function (Bill $record, array $data): void {
                        TransactionBillLinkForm::updatePaidAmount(
                            $this->ownerRecord,
                            $record,
                            (float) $data['amount_paid'],
                        );

                        TransactionEditPageRefresh::refresh($this);
                    }),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove bill from transaction')
                    ->modalDescription('This unlinks the bill from this transaction and recalculates its paid status.')
                    ->action(function (Bill $record): void {
                        TransactionBillLinkForm::detachBill($this->ownerRecord, $record);

                        TransactionEditPageRefresh::refresh($this);
                    }),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}
