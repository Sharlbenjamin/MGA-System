<?php

namespace App\Filament\Resources\TransactionResource\RelationManager;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvoiceRelationManager extends RelationManager
{

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'Income';
    }

    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

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
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_amount')
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

                TextInputColumn::make('amount_paid')
                    ->label('Paid Amount')
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->rules(['numeric', 'min:0'])
                    ->summarize(
                        Sum::make()
                            ->query(function ($query) {
                                return $query instanceof Builder
                                    ? $query->select('invoice_transaction.amount_paid')
                                    : $query->selectRaw('SUM(amount_paid)');
                            })
                    )
                    ->updateStateUsing(function (Model $record, $state): float {
                        $paid = round(floatval($state), 2);
                        $currentPivot = (float) ($record->amount_paid ?? 0);
                        $totalPaid = (float) DB::table('invoice_transaction')
                            ->where('invoice_id', $record->id)
                            ->sum('amount_paid');
                        $maxAllowed = round((float) $record->total_amount - ($totalPaid - $currentPivot), 2);
                        $paid = min($paid, max(0, $maxAllowed));

                        $this->ownerRecord->updateInvoicePaidAmount($record, $paid);
                        $this->resetTable();

                        return $paid;
                    }),

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
            ->defaultSort('name')
            ->paginated(false);
    }
}
