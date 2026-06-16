<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Facades\Excel;

class SantanderMovimientosImport implements ToCollection, WithHeadingRow, WithStartRow
{
    public Collection $rows;

    protected int $headerRow = 8;

    public function __construct(?int $headerRow = null)
    {
        if ($headerRow !== null) {
            $this->headerRow = $headerRow;
        }
    }

    public function startRow(): int
    {
        return $this->headerRow;
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows->map(function ($row) {
            $array = $row instanceof Collection ? $row->toArray() : (array) $row;

            $item = $this->pick($array, ['item', 'item']);
            $reference1 = trim((string) $this->pick($array, ['reference_1', 'reference1']));
            $reference2 = trim((string) $this->pick($array, ['reference_2', 'reference2']));
            $documentNo = trim((string) $this->pick($array, ['document_no', 'document_no']));
            $additional = trim((string) $this->pick($array, ['additional_information', 'additional_information']));

            $referenceParts = array_filter([$reference1, $reference2, $documentNo]);
            $reference = $referenceParts !== [] ? implode(' | ', $referenceParts) : null;

            $description = $item;
            if ($additional !== '') {
                $description = trim($description."\n".$additional);
            }

            $rawAmount = $this->pick($array, ['amount', 'importe', 'import']);

            return [
                'transaction_date' => $this->pick($array, ['transaction_date', 'date', 'fecha']),
                'value_date' => $this->pick($array, ['value_date']),
                'amount' => $rawAmount,
                'signed_amount' => is_numeric($rawAmount) ? (float) $rawAmount : null,
                'reference' => $reference,
                'description' => $description !== '' ? $description : null,
                'bank_code' => trim((string) $this->pick($array, ['code', 'codigo'])),
            ];
        })->filter(fn ($row) => $row['transaction_date'] !== null || $row['amount'] !== null);
    }

    public static function detect(string $fullPath): bool
    {
        return self::resolveHeaderRow($fullPath) > 0;
    }

    public static function resolveHeaderRow(string $fullPath): int
    {
        $sheets = Excel::toArray(new \stdClass, $fullPath);
        $rows = $sheets[0] ?? [];

        foreach ($rows as $index => $row) {
            $joined = Str::lower(implode(' ', array_map(fn ($v) => trim((string) $v), $row)));

            if (str_contains($joined, 'transaction date') && str_contains($joined, 'amount')) {
                return $index + 1;
            }
        }

        return 0;
    }

    protected function pick(array $array, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array) && $array[$key] !== null && $array[$key] !== '') {
                return $array[$key];
            }
        }

        return null;
    }
}
