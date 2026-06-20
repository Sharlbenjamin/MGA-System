<?php

namespace App\Services;

use Illuminate\Support\Str;

class TransactionImportColumnMap
{
    /**
     * Canonical internal field keys accepted after normalization.
     *
     * @var array<int, string>
     */
    public const CANONICAL_FIELDS = [
        'row_id',
        'transaction_date',
        'debit',
        'credit',
        'amount',
        'type',
        'name',
        'reference',
        'description',
        'notes',
        'related_type',
        'client_name',
        'provider_name',
        'branch_name',
        'documentation_category',
        'payment_status',
        'bank_charges',
        'charges_covered_by_client',
        'invoice_numbers',
        'invoice_amounts_paid',
        'bill_names',
        'bill_amounts_paid',
        'receipt_url',
        'mark_revised',
    ];

    /**
     * Header alias => canonical field (lowercase trimmed headers).
     *
     * @var array<string, string>
     */
    protected const HEADER_ALIASES = [
        'row_id' => 'row_id',
        'id' => 'row_id',
        '# ' => 'row_id',

        'transaction_date' => 'transaction_date',
        'transaction date' => 'transaction_date',
        'date' => 'transaction_date',
        'fecha' => 'transaction_date',
        'fecha operacion' => 'transaction_date',
        'fecha operación' => 'transaction_date',
        'fecha valor' => 'transaction_date',
        'operation date' => 'transaction_date',

        'debit' => 'debit',
        'debe' => 'debit',
        'cargo' => 'debit',
        'withdrawal' => 'debit',

        'credit' => 'credit',
        'haber' => 'credit',
        'abono' => 'credit',
        'deposit' => 'credit',

        'amount' => 'amount',
        'importe' => 'amount',
        'value' => 'amount',

        'type' => 'type',
        'transaction type' => 'type',
        'tipo' => 'type',

        'name' => 'name',
        'nombre' => 'name',

        'reference' => 'reference',
        'referencia' => 'reference',
        'ref' => 'reference',

        'description' => 'description',
        'descripcion' => 'description',
        'descripción' => 'description',
        'concepto' => 'description',
        'details' => 'description',
        'detail' => 'description',

        'notes' => 'notes',
        'notas' => 'notes',

        'related_type' => 'related_type',

        'client_name' => 'client_name',
        'client' => 'client_name',
        'cliente' => 'client_name',

        'provider_name' => 'provider_name',
        'provider' => 'provider_name',
        'proveedor' => 'provider_name',

        'branch_name' => 'branch_name',
        'branch' => 'branch_name',

        'documentation_category' => 'documentation_category',
        'category' => 'documentation_category',

        'payment_status' => 'payment_status',
        'status' => 'payment_status',

        'bank_charges' => 'bank_charges',
        'charges_covered_by_client' => 'charges_covered_by_client',

        'invoice_numbers' => 'invoice_numbers',
        'invoices' => 'invoice_numbers',

        'invoice_amounts_paid' => 'invoice_amounts_paid',

        'bill_names' => 'bill_names',
        'bills' => 'bill_names',

        'bill_amounts_paid' => 'bill_amounts_paid',

        'receipt_url' => 'receipt_url',
        'attachment' => 'receipt_url',

        'mark_revised' => 'mark_revised',
    ];

    /**
     * @return array<int, string>
     */
    public static function templateHeadings(): array
    {
        return [
            'transaction_date',
            'debit',
            'credit',
            'reference',
            'description',
            'type',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function templateExampleRows(): array
    {
        return [
            ['2025-03-15', '', '1250.00', 'TRF CLIENT ABC', 'Client payment received', 'Income'],
            ['2025-03-16', '500.00', '', 'TRF HOSPITAL', 'Provider transfer', 'Outflow'],
            ['2025-03-17', '45.00', '', 'Tarjeta 1234', 'Tarjeta petrol station', 'Outflow'],
        ];
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array<string, int> canonical field => column index
     */
    public function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            if ($header === null || trim((string) $header) === '') {
                continue;
            }

            $key = $this->normalizeHeaderKey((string) $header);
            $canonical = self::HEADER_ALIASES[$key]
                ?? self::HEADER_ALIASES[str_replace(' ', '_', $key)]
                ?? null;

            if ($canonical !== null && ! array_key_exists($canonical, $map)) {
                $map[$canonical] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     * @return array<string, mixed>
     */
    public function normalizeRow(array $row, array $headerMap): array
    {
        $normalized = [];

        foreach ($headerMap as $field => $index) {
            $value = $row[$index] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                $value = null;
            }

            $normalized[$field] = $value;
        }

        if (blank($normalized['notes'] ?? null) && filled($normalized['description'] ?? null)) {
            $normalized['notes'] = $normalized['description'];
        }

        if (blank($normalized['description'] ?? null) && filled($normalized['notes'] ?? null)) {
            $normalized['description'] = $normalized['notes'];
        }

        return $normalized;
    }

    protected function normalizeHeaderKey(string $header): string
    {
        $header = Str::lower(trim($header));
        $header = str_replace(['_', '-'], ' ', $header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return $header;
    }
}
