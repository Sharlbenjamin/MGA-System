<?php

namespace Tests\Unit;

use App\Services\TransactionDocumentationService;
use App\Services\TransactionImportColumnMap;
use App\Services\TransactionImportService;
use PHPUnit\Framework\TestCase;

class TransactionImportServiceTest extends TestCase
{
    public function test_column_map_recognizes_export_headers(): void
    {
        $map = new TransactionImportColumnMap;
        $headers = ['transaction_date', 'amount', 'reference', 'description'];

        $headerMap = $map->mapHeaders($headers);

        $this->assertSame(0, $headerMap['transaction_date']);
        $this->assertSame(1, $headerMap['amount']);
        $this->assertSame(2, $headerMap['reference']);
        $this->assertSame(3, $headerMap['description']);
    }

    public function test_detects_card_payment_from_description(): void
    {
        $this->assertTrue(
            TransactionDocumentationService::isCardPaymentBankText('Tarjeta 1234 petrol', null)
        );
    }

    public function test_same_fingerprint_detects_in_file_duplicates(): void
    {
        $service = new TransactionImportService;
        $row = [
            'transaction_date' => '2025-03-16',
            'debit' => '100.00',
            'reference' => 'DUPE',
            'description' => 'Test',
        ];

        $this->assertSame($service->rowFingerprint($row), $service->rowFingerprint($row));
    }

    public function test_row_fingerprint_is_stable(): void
    {
        $service = new TransactionImportService;
        $row = [
            'transaction_date' => '2025-01-10',
            'amount' => '50.00',
            'reference' => 'ABC',
        ];

        $this->assertSame(
            $service->rowFingerprint($row),
            $service->rowFingerprint($row),
        );
    }

    public function test_normalize_row_copies_description_to_notes(): void
    {
        $map = new TransactionImportColumnMap;
        $headerMap = $map->mapHeaders(['transaction_date', 'description']);
        $normalized = $map->normalizeRow(['2025-03-01', 'Bank line text'], $headerMap);

        $this->assertSame('Bank line text', $normalized['description']);
        $this->assertSame('Bank line text', $normalized['notes']);
    }
}
