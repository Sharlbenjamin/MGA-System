<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TrxOutStatementNormalizer
{
    /**
     * @var array<string, string>
     */
    protected const HEADER_MAP = [
        'value date' => 'transaction_date',
        'date' => '_skip_date_formula',
        'item:' => 'description',
        'item' => 'description',
        'amount' => 'amount',
        'reason' => 'reason',
        'category' => 'sheet_category',
        'type' => 'sheet_type',
        'bills available' => 'bills_available',
        'reference 1' => 'reference',
        'document no.' => 'reference',
    ];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parseFile(string $absolutePath): Collection
    {
        $import = new class implements \Maatwebsite\Excel\Concerns\ToArray
        {
            public function array(array $array): array
            {
                return $array;
            }
        };

        $rows = collect(Excel::toArray($import, $absolutePath)[0] ?? []);

        if ($rows->isEmpty()) {
            return collect();
        }

        $columnMap = $this->mapHeaders(is_array($rows->first()) ? $rows->first() : []);

        return $rows->slice(1)
            ->values()
            ->map(fn (array $row, int $index): array => $this->normalizeRawRow($row, $columnMap, $index + 2))
            ->filter(fn (array $row): bool => filled($row['description'] ?? null) || filled($row['amount'] ?? null))
            ->values();
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<int, string>
     */
    protected function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $key = Str::lower(trim((string) $header));

            if ($key === '' || ! isset(self::HEADER_MAP[$key])) {
                continue;
            }

            $map[$index] = self::HEADER_MAP[$key];
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, string>  $columnMap
     * @return array<string, mixed>
     */
    protected function normalizeRawRow(array $row, array $columnMap, int $rowNumber): array
    {
        $normalized = ['_index' => $rowNumber];

        foreach ($columnMap as $index => $field) {
            if ($field === '_skip_date_formula') {
                continue;
            }

            $value = $row[$index] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$field] = is_string($value) ? trim($value) : $value;
        }

        $description = (string) ($normalized['description'] ?? '');
        $sheetType = (string) ($normalized['sheet_type'] ?? '');
        $sheetCategory = (string) ($normalized['sheet_category'] ?? '');
        $reason = (string) ($normalized['reason'] ?? '');

        $normalized['transaction_date'] = $this->resolveDate($normalized['transaction_date'] ?? null);
        $normalized['amount'] = $this->parseAmount($normalized['amount'] ?? null);
        $normalized['reference'] = $this->resolveReference($normalized, $description);
        $normalized['name'] = Str::limit($description, 255, '');
        $normalized['notes'] = $reason !== '' ? $reason : null;
        $normalized['_formula_category'] = str_starts_with($sheetCategory, '=');

        $mapping = $this->mapToSystemFields($sheetType, $sheetCategory, $description, $reason);
        $normalized['type'] = $mapping['type'];
        $normalized['documentation_category'] = $mapping['documentation_category'];
        $normalized['related_type'] = $mapping['related_type'];

        unset($normalized['sheet_type'], $normalized['sheet_category'], $normalized['reason'], $normalized['bills_available']);

        return $normalized;
    }

    /**
     * @return array{type: string, documentation_category: string, related_type: string}
     */
    public function mapToSystemFields(string $sheetType, string $sheetCategory, string $description, string $reason): array
    {
        $typeKey = Str::lower(trim($sheetType));
        $categoryKey = $this->resolveSheetCategory($sheetCategory, $description);
        $text = mb_strtolower($description);
        $isCard = TransactionDocumentationService::isCardPaymentBankText($description);
        $isTransfer = str_contains($text, 'transferencia a favor de') || str_contains($text, 'transferencia a ');

        return match ($typeKey) {
            'expenses' => [
                'type' => 'Expense',
                'documentation_category' => 'expense_payment',
                'related_type' => $this->mapExpenseRelatedType($categoryKey, $reason, $description),
            ],
            'patient refund' => [
                'type' => 'Outflow',
                'documentation_category' => 'patient_refund',
                'related_type' => 'Patient',
            ],
            'refund amount' => [
                'type' => 'Outflow',
                'documentation_category' => 'capital_return',
                'related_type' => 'Other',
            ],
            default => $this->mapProviderOutflow($categoryKey, $isCard, $isTransfer),
        };
    }

    /**
     * @return array{type: string, documentation_category: string, related_type: string}
     */
    protected function mapProviderOutflow(string $categoryKey, bool $isCard, bool $isTransfer): array
    {
        if ($isCard || $categoryKey === 'card') {
            return [
                'type' => 'Outflow',
                'documentation_category' => 'card_provider',
                'related_type' => 'Provider',
            ];
        }

        if ($categoryKey === 'transfer' || $categoryKey === 'single' || $isTransfer) {
            return [
                'type' => 'Outflow',
                'documentation_category' => 'provider_single',
                'related_type' => 'Provider',
            ];
        }

        return [
            'type' => 'Outflow',
            'documentation_category' => 'provider_single',
            'related_type' => 'Provider',
        ];
    }

    protected function resolveSheetCategory(string $sheetCategory, string $description): string
    {
        if ($sheetCategory !== '' && ! str_starts_with($sheetCategory, '=')) {
            $key = Str::lower(trim($sheetCategory));

            return $key === 'trasnfer' ? 'transfer' : $key;
        }

        if (TransactionDocumentationService::isCardPaymentBankText($description)) {
            return 'card';
        }

        if (str_contains(mb_strtolower($description), 'transferencia a')) {
            return 'transfer';
        }

        return 'single';
    }

    protected function mapExpenseRelatedType(string $categoryKey, string $reason, string $description): string
    {
        $combined = mb_strtolower("{$categoryKey} {$reason} {$description}");

        return match (true) {
            $categoryKey === 'rent' || str_contains($combined, 'rent') => 'Rent',
            in_array($categoryKey, ['lawyer', 'bekham law'], true) || str_contains($combined, 'lawyer') => 'Lawyer',
            str_contains($combined, 'tax') || str_contains($combined, 'legal partener') => 'Legal',
            str_contains($combined, 'register') || str_contains($combined, 'registration') => 'Legal',
            str_contains($combined, 'cloudways') || str_contains($combined, 'google') || str_contains($combined, 'g suit') => 'Utility',
            str_contains($combined, 'salary') || str_contains($combined, 'nomina') => 'Salary',
            str_contains($combined, 'insurance') => 'Insurance',
            str_contains($combined, 'marketing') => 'Marketing',
            str_contains($combined, 'travel') => 'Travel',
            str_contains($combined, 'accounting') => 'Accounting',
            default => 'Other',
        };
    }

    protected function resolveDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 40000) {
            try {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                )->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', trim((string) $value), $matches)) {
                $year = strlen($matches[3]) === 2 ? '20'.$matches[3] : $matches[3];

                return sprintf('%04d-%02d-%02d', (int) $year, (int) $matches[2], (int) $matches[1]);
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round(abs((float) $value), 2);
        }

        $normalized = str_replace(['€', ' ', "\xc2\xa0"], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^\d.\-]/', '', $normalized) ?? '';

        return is_numeric($normalized) ? round(abs((float) $normalized), 2) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveReference(array $row, string $description): ?string
    {
        foreach (['reference'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '' && ! str_contains(strtolower($value), 'e+')) {
                return $value;
            }
        }

        if (preg_match('/referencia:\s*([^\s,]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }

        return Str::limit(trim($description), 120, '') ?: null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    public function summarize(Collection $rows): array
    {
        $typeCounts = $rows->countBy(fn (array $row): string => (string) ($row['type'] ?? 'unknown'));
        $categoryCounts = $rows->countBy(fn (array $row): string => (string) ($row['documentation_category'] ?? 'unknown'));
        $relatedCounts = $rows->countBy(fn (array $row): string => (string) ($row['related_type'] ?? 'unknown'));

        return [
            'total_rows' => $rows->count(),
            'total_amount' => round($rows->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2),
            'outflow_rows' => (int) ($typeCounts['Outflow'] ?? 0),
            'expense_rows' => (int) ($typeCounts['Expense'] ?? 0),
            'card_provider_rows' => (int) ($categoryCounts['card_provider'] ?? 0),
            'provider_single_rows' => (int) ($categoryCounts['provider_single'] ?? 0),
            'expense_payment_rows' => (int) ($categoryCounts['expense_payment'] ?? 0),
            'provider_related_rows' => (int) ($relatedCounts['Provider'] ?? 0),
            'rent_expense_rows' => $rows->where('related_type', 'Rent')->count(),
            'utility_expense_rows' => $rows->where('related_type', 'Utility')->count(),
            'lawyer_expense_rows' => $rows->where('related_type', 'Lawyer')->count(),
            'patient_refund_rows' => (int) ($categoryCounts['patient_refund'] ?? 0),
            'capital_return_rows' => (int) ($categoryCounts['capital_return'] ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function countFormulaCategoryRows(Collection $rows): int
    {
        return $rows->where('_formula_category', true)->count();
    }
}
