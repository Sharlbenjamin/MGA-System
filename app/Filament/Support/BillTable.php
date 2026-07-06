<?php

namespace App\Filament\Support;

use App\Models\Bill;
use Filament\Tables;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;

class BillTable
{
    /** @return list<string> */
    public static function eagerLoadRelations(): array
    {
        return [
            'file.patient',
            'file.serviceType',
        ];
    }

    public static function applyNameSearch(Builder $query, string $search): Builder
    {
        $search = addcslashes($search, '%_');

        return $query->where(function (Builder $inner) use ($search): void {
            $inner->where('bills.name', 'like', "%{$search}%")
                ->orWhereHas('file', function (Builder $fileQuery) use ($search): void {
                    $fileQuery->where('mga_reference', 'like', "%{$search}%");
                });
        });
    }

    public static function nameColumn(bool $editable = false): Column
    {
        if ($editable) {
            return Tables\Columns\TextInputColumn::make('name')
                ->label('Bill')
                ->getStateUsing(fn (Bill $record): string => $record->hasCustomName() ? $record->name : '')
                ->updateStateUsing(function (Bill $record, ?string $state): void {
                    $record->update(['name' => filled($state) ? $state : '']);
                })
                ->placeholder(fn (Bill $record): string => $record->display_name)
                ->searchable(query: fn (Builder $query, string $search): Builder => static::applyNameSearch($query, $search))
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    return $query
                        ->leftJoin('files', 'files.id', '=', 'bills.file_id')
                        ->orderByRaw(
                            "COALESCE(NULLIF(bills.name, ''), files.mga_reference) {$direction}",
                        )
                        ->select('bills.*');
                });
        }

        return Tables\Columns\TextColumn::make('display_name')
            ->label('Bill')
            ->searchable(query: fn (Builder $query, string $search): Builder => static::applyNameSearch($query, $search))
            ->sortable(query: function (Builder $query, string $direction): Builder {
                return $query
                    ->leftJoin('files', 'files.id', '=', 'bills.file_id')
                    ->orderByRaw(
                        "COALESCE(NULLIF(bills.name, ''), files.mga_reference) {$direction}",
                    )
                    ->select('bills.*');
            });
    }

    public static function patientColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('file.patient.name')
            ->label('Patient / DOB')
            ->searchable()
            ->sortable()
            ->description(fn (Bill $record): string => $record->file?->patient?->dob?->format('d/m/Y') ?? '—');
    }

    public static function serviceColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('file.service_date')
            ->label('Service / Type')
            ->date('d/m/Y')
            ->sortable()
            ->description(fn (Bill $record): string => $record->file?->serviceType?->name ?? '—');
    }

    public static function paymentColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('paid_amount')
            ->label('Paid / Date / Status')
            ->money('EUR')
            ->sortable()
            ->description(function (Bill $record): string {
                $date = $record->payment_date?->format('d/m/Y') ?? '—';

                return "{$date} · {$record->status}";
            });
    }

    public static function totalColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('total_amount')
            ->label('Total / Remaining')
            ->money('EUR')
            ->sortable()
            ->description(fn (Bill $record): string => 'Remaining: €' . number_format($record->remaining_amount, 2));
    }

    /** @return list<Column> */
    public static function relationManagerColumns(bool $editableName = true): array
    {
        return [
            static::nameColumn($editableName),
            static::patientColumn(),
            static::serviceColumn(),
            static::paymentColumn(),
            static::totalColumn(),
        ];
    }
}
