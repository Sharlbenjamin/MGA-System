<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TransactionImportService
{
    public function __construct(
        protected TransactionDocumentationService $documentationService
    ) {}

    public function isDuplicate(array $row, ?string $type = null): bool
    {
        $date = $this->parseDate($row['transaction_date'] ?? $row['date'] ?? null);
        $amount = $this->parseAmount($row['amount'] ?? null);

        if (! $date || $amount === null) {
            return false;
        }

        $reference = $this->documentationService->normalizeReference(
            $row['reference'] ?? $row['description'] ?? null
        );

        $query = Transaction::query()
            ->whereDate('date', $date)
            ->where('amount', $amount);

        if ($type) {
            $query->where('type', $type);
        }

        if ($reference) {
            $query->where(function ($q) use ($reference) {
                $q->whereRaw('LOWER(TRIM(reference)) = ?', [$reference])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', [$reference])
                    ->orWhereRaw('LOWER(TRIM(notes)) = ?', [$reference]);
            });
        }

        return $query->exists();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{new: Collection, duplicates_existing: Collection, duplicates_in_file: Collection}
     */
    public function classifyRows(Collection $rows): array
    {
        $new = collect();
        $duplicatesExisting = collect();
        $duplicatesInFile = collect();
        $seenKeys = [];

        foreach ($rows as $index => $row) {
            $row['_index'] = $index;
            $key = $this->rowFingerprint($row);

            if (isset($seenKeys[$key])) {
                $duplicatesInFile->push($row);

                continue;
            }

            $seenKeys[$key] = true;

            if ($this->isDuplicate($row)) {
                $duplicatesExisting->push($row);

                continue;
            }

            $new->push($row);
        }

        return [
            'new' => $new,
            'duplicates_existing' => $duplicatesExisting,
            'duplicates_in_file' => $duplicatesInFile,
        ];
    }

    public function rowFingerprint(array $row): string
    {
        $date = $this->parseDate($row['transaction_date'] ?? $row['date'] ?? null)?->format('Y-m-d') ?? '';
        $amount = (string) $this->parseAmount($row['amount'] ?? null);
        $ref = $this->documentationService->normalizeReference($row['reference'] ?? $row['description'] ?? '') ?? '';

        return md5("{$date}|{$amount}|{$ref}");
    }

    public function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof Carbon) {
                return $value->startOfDay();
            }

            if (is_numeric($value)) {
                return Carbon::createFromTimestampUTC((int) (($value - 25569) * 86400))->startOfDay();
            }

            if (is_string($value) && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', trim($value))) {
                return Carbon::createFromFormat('d/m/Y', trim($value))->startOfDay();
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round(abs((float) $value), 2);
        }

        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? round(abs((float) $cleaned), 2) : null;
    }

    public function parseSignedAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? round((float) $cleaned, 2) : null;
    }

    public function inferTypeFromRow(array $row): string
    {
        $code = str_pad(trim((string) ($row['bank_code'] ?? '')), 3, '0', STR_PAD_LEFT);

        $inferred = match ($code) {
            '071', '119', '135' => 'Income',
            '072', '136' => 'Outflow',
            '120' => 'Expense',
            '001' => 'Income',
            default => null,
        };

        if ($inferred !== null) {
            return $inferred;
        }

        $signed = $row['signed_amount'] ?? $this->parseSignedAmount($row['amount'] ?? null);

        if ($signed !== null) {
            return $signed >= 0 ? 'Income' : 'Outflow';
        }

        return 'Income';
    }

    public function defaultRelatedType(string $type): string
    {
        return match ($type) {
            'Income' => 'Client',
            'Outflow' => 'Provider',
            'Expense' => 'Other',
            default => 'Client',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeImportRow(array $row): array
    {
        $signed = $row['signed_amount'] ?? $this->parseSignedAmount($row['amount'] ?? null);
        $amount = $this->parseAmount($row['amount'] ?? $signed);
        $date = $this->parseDate($row['transaction_date'] ?? null);
        $type = $row['type'] ?? $this->inferTypeFromRow(array_merge($row, [
            'signed_amount' => $signed,
        ]));

        $reference = trim((string) ($row['reference'] ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($row['description'] ?? ''));
        }

        $description = trim((string) ($row['description'] ?? ''));

        return [
            'transaction_date' => $date?->format('Y-m-d'),
            'amount' => $amount,
            'signed_amount' => $signed,
            'reference' => $reference !== '' ? $reference : null,
            'description' => $description !== '' ? $description : null,
            'bank_code' => $row['bank_code'] ?? null,
            'value_date' => $row['value_date'] ?? null,
            'type' => $type,
            'related_type' => $row['related_type'] ?? $this->defaultRelatedType($type),
            'needs_review' => ($row['bank_code'] ?? '') === '001',
        ];
    }
}
