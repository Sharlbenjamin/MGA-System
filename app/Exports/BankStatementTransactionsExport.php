<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BankStatementTransactionsExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths
{
    public function __construct(
        protected Builder $query,
    ) {}

    public function query(): Builder
    {
        return (clone $this->query)->orderBy('date')->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'transaction_date',
            'amount',
            'reference',
            'description',
        ];
    }

    /**
     * @param  \App\Models\Transaction  $transaction
     */
    public function map($transaction): array
    {
        $reference = $transaction->reference ?: $transaction->name;
        $description = $transaction->notes ?: $transaction->name ?: $reference;

        return [
            $transaction->date?->format('Y-m-d'),
            round(abs((float) $transaction->amount), 2),
            $reference,
            $description,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 14,
            'C' => 28,
            'D' => 40,
        ];
    }
}
