<?php

namespace App\Filament\Resources\TransactionResource\RelationManager;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class BillRelationManager extends RelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'Outflow' || $ownerRecord->type === 'Expense';
    }

    protected static string $relationship = 'bills';

    protected static ?string $title = 'Bills';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) =>
                $query->select(
                    'bills.*',
                    'bill_transaction.amount_paid',
                    DB::raw('(bills.total_amount - COALESCE(bill_transaction.amount_paid, 0)) as remaining_amount')
                )
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->query(function ($query) {
                                return $query instanceof Builder
                                    ? $query->select('bills.total_amount')
                                    : $query->selectRaw('SUM(bills.total_amount)');
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
                                    ? $query->select('bill_transaction.amount_paid')
                                    : $query->selectRaw('SUM(bill_transaction.amount_paid)');
                            })
                    )
                    ->afterStateUpdated(function (Model $record, $state) {
                        $total = $record->total_amount;
                        $paid = floatval($state);

                        $status = match (true) {
                            $paid >= $total => 'Paid',
                            $paid > 0 && $paid < $total => 'Partial',
                            default => 'Unpaid',
                        };

                        // Update the intermediate table
                        DB::table('bill_transaction')
                            ->where('bill_id', $record->id)
                            ->where('transaction_id', $this->ownerRecord->id)
                            ->update(['amount_paid' => $paid]);

                        // Update the bill status
                        $record->status = $status;
                        $record->save();

                        // Refresh the record to get updated values
                        $record->refresh();
                    }),
                TextColumn::make('remaining_amount')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->query(function ($query) {
                                return $query instanceof Builder
                                    ? $query->selectRaw('SUM(bills.total_amount - bill_transaction.amount_paid)')
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
            ]);
    }
}
