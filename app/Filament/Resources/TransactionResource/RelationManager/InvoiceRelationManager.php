<?php

namespace App\Filament\Resources\TransactionResource\RelationManager;

use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Support\TransactionEditPageRefresh;
use App\Filament\Support\TransactionInvoiceLinkForm;
use App\Models\Invoice;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InvoiceRelationManager extends RelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'Income'
            && $pageClass === EditTransaction::class;
    }

    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) =>
                $query->select(
                    'invoices.*',
                    'invoice_transaction.amount_paid',
                    DB::raw('(invoices.total_amount - COALESCE((
                        SELECT SUM(it.amount_paid)
                        FROM invoice_transaction it
                        WHERE it.invoice_id = invoices.id
                    ), 0)) as remaining_amount')
                )
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Invoice')
                    ->description(fn (Invoice $record): string => ($record->invoice_date?->format('d/m/Y') ?? '—').' · '.$record->status)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Original amount')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(function ($query) {
                                return $query instanceof Builder
                                    ? $query->select('invoices.total_amount')
                                    : $query->selectRaw('SUM(invoices.total_amount)');
                            })
                            ->money('EUR')
                    ),
                TextColumn::make('amount_paid')
                    ->label('Paid on this transaction')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(function ($query) {
                                return $query instanceof Builder
                                    ? $query->select('invoice_transaction.amount_paid')
                                    : $query->selectRaw('SUM(amount_paid)');
                            })
                    ),
                TextColumn::make('remaining_amount')
                    ->label('Invoice remaining')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(function ($query) {
                                return $query instanceof Builder
                                    ? $query->selectRaw('SUM(invoices.total_amount - COALESCE((
                                        SELECT SUM(it.amount_paid)
                                        FROM invoice_transaction it
                                        WHERE it.invoice_id = invoices.id
                                    ), 0))')
                                    : $query->selectRaw('SUM(remaining_amount)');
                            })
                            ->money('EUR')
                    ),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Unpaid' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addInvoice')
                    ->label('Add invoice')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add invoice')
                    ->modalSubmitActionLabel('Add')
                    ->form(fn (): array => TransactionInvoiceLinkForm::attachFormSchema(
                        $this->ownerRecord,
                        $this->ownerRecord->invoices()->pluck('invoices.id')->all(),
                    ))
                    ->action(function (array $data): void {
                        TransactionInvoiceLinkForm::attachInvoice(
                            $this->ownerRecord,
                            (int) $data['invoice_id'],
                            (float) $data['amount_paid'],
                        );

                        TransactionEditPageRefresh::refresh($this->getLivewire());
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('editPaidAmount')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit paid amount')
                    ->fillForm(fn (Invoice $record): array => [
                        'amount_paid' => (float) ($record->amount_paid ?? 0),
                    ])
                    ->form(fn (Invoice $record): array => TransactionInvoiceLinkForm::editPaidAmountSchema($record))
                    ->action(function (Invoice $record, array $data): void {
                        TransactionInvoiceLinkForm::updatePaidAmount(
                            $this->ownerRecord,
                            $record,
                            (float) $data['amount_paid'],
                        );

                        TransactionEditPageRefresh::refresh($this->getLivewire());
                    }),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove invoice from transaction')
                    ->modalDescription('This unlinks the invoice from this transaction and recalculates its paid status.')
                    ->action(function (Invoice $record): void {
                        TransactionInvoiceLinkForm::detachInvoice($this->ownerRecord, $record);

                        TransactionEditPageRefresh::refresh($this->getLivewire());
                    }),
            ])
            ->defaultSort('name')
            ->paginated(false);
    }
}
