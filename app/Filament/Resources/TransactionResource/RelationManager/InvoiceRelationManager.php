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
    protected static bool $isLazy = true;

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
            ->modifyQueryUsing(function (Builder $query): void {
                $query->select(
                    'invoices.*',
                    'invoice_transaction.amount_paid',
                )->selectSub(
                    DB::table('invoice_transaction')
                        ->selectRaw('COALESCE(SUM(amount_paid), 0)')
                        ->whereColumn('invoice_id', 'invoices.id'),
                    'invoice_total_paid',
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Invoice number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Original amount')
                    ->money('EUR')
                    ->summarize(Sum::make()->money('EUR')),
                TextColumn::make('amount_paid')
                    ->label('Paid on this transaction')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(fn ($query) => $query->selectRaw('SUM(invoice_transaction.amount_paid)'))
                    ),
                TextColumn::make('invoice_remaining')
                    ->label('Invoice remaining')
                    ->money('EUR')
                    ->getStateUsing(fn (Invoice $record): float => max(
                        0,
                        round((float) $record->total_amount - (float) ($record->invoice_total_paid ?? 0), 2),
                    )),
                TextColumn::make('status')
                    ->label('Status')
                    ->description(fn (Invoice $record): string => $record->invoice_date?->format('d/m/Y') ?? '—')
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

                        TransactionEditPageRefresh::refresh($this);
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

                        TransactionEditPageRefresh::refresh($this);
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

                        TransactionEditPageRefresh::refresh($this);
                    }),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}
