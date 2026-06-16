<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class TransactionExcelImporter
{
    public static function loadRows(string $fullPath): Collection
    {
        if (SantanderMovimientosImport::detect($fullPath)) {
            $headerRow = SantanderMovimientosImport::resolveHeaderRow($fullPath);
            $import = new SantanderMovimientosImport($headerRow > 0 ? $headerRow : 8);
            Excel::import($import, $fullPath);

            return $import->rows;
        }

        $import = new TransactionImport;
        Excel::import($import, $fullPath);

        return $import->rows->map(function (array $row) {
            $rawAmount = $row['amount'] ?? null;
            $signed = is_numeric($rawAmount) ? (float) $rawAmount : null;

            return array_merge($row, [
                'signed_amount' => $signed,
                'bank_code' => null,
                'value_date' => null,
            ]);
        });
    }
}
