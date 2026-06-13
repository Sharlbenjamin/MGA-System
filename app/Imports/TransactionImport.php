<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TransactionImport implements ToCollection, WithHeadingRow
{
    public Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows->map(function ($row) {
            $array = $row instanceof Collection ? $row->toArray() : (array) $row;

            return [
                'transaction_date' => $array['transaction_date'] ?? $array['date'] ?? $array['fecha'] ?? null,
                'amount' => $array['amount'] ?? $array['importe'] ?? $array['import'] ?? null,
                'reference' => $array['reference'] ?? $array['referencia'] ?? null,
                'description' => $array['description'] ?? $array['descripcion'] ?? $array['concepto'] ?? null,
            ];
        })->filter(fn ($row) => $row['transaction_date'] !== null || $row['amount'] !== null);
    }
}
