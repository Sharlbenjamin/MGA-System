<?php

namespace Tests\Unit;

use App\Services\TrxOutStatementNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TrxOutStatementNormalizerTest extends TestCase
{
    #[Test]
    public function it_maps_provider_card_purchase_to_card_provider_outflow(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Provider',
            'Card',
            'Compra Air Doctor, Nicosia, Tarjeta 4176570171221270 , Comision 0',
            'Air Doctor',
        );

        $this->assertSame('Outflow', $mapping['type']);
        $this->assertSame('card_provider', $mapping['documentation_category']);
        $this->assertSame('Provider', $mapping['related_type']);
    }

    #[Test]
    public function it_maps_provider_transfer_to_provider_single(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Provider',
            'Transfer',
            'Transferencia A Favor De Dublin Health Clinic, Concepto Invoice 100',
            'Dublin Health clinic',
        );

        $this->assertSame('Outflow', $mapping['type']);
        $this->assertSame('provider_single', $mapping['documentation_category']);
        $this->assertSame('Provider', $mapping['related_type']);
    }

    #[Test]
    public function it_maps_expense_rent_to_expense_payment_rent(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Expenses',
            'Rent',
            'Transferencia A Favor De Sharl Hany, Referencia: 001',
            'Rent 1',
        );

        $this->assertSame('Expense', $mapping['type']);
        $this->assertSame('expense_payment', $mapping['documentation_category']);
        $this->assertSame('Rent', $mapping['related_type']);
    }

    #[Test]
    public function it_infers_card_category_when_spreadsheet_formula_was_not_evaluated(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Provider',
            '=IF(OR(ISNUMBER(SEARCH("Tarjeta", C5)), ISNUMBER(SEARCH("tarj", C5))), "Card", "Transfer")',
            'Compra Cloudways, Amsterdam, Tarjeta 4176570171221270',
            'Cloudways',
        );

        $this->assertSame('card_provider', $mapping['documentation_category']);
    }

    #[Test]
    public function it_maps_patient_refund_to_outflow_patient_refund_patient(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Patient Refund',
            'Transfer',
            'Transferencia A Favor De John Smith, Referencia: 001',
            'John Smith refund',
        );

        $this->assertSame('Outflow', $mapping['type']);
        $this->assertSame('patient_refund', $mapping['documentation_category']);
        $this->assertSame('Patient', $mapping['related_type']);
    }

    #[Test]
    public function it_maps_refund_amount_to_capital_return(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Refund Amount',
            'Transfer',
            'Transferencia A Favor De Owner Account, Concepto Return funds',
            'Return funds',
        );

        $this->assertSame('Outflow', $mapping['type']);
        $this->assertSame('capital_return', $mapping['documentation_category']);
        $this->assertSame('Other', $mapping['related_type']);
    }

    #[Test]
    public function it_normalizes_trasnfer_typo_to_transfer(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $mapping = $normalizer->mapToSystemFields(
            'Provider',
            'Trasnfer',
            'Transferencia A Favor De Clinic X, Referencia: 001',
            'Clinic X',
        );

        $this->assertSame('provider_single', $mapping['documentation_category']);
    }

    #[Test]
    public function it_sets_name_from_item_and_notes_from_reason_only(): void
    {
        $normalizer = new TrxOutStatementNormalizer;
        $method = new \ReflectionMethod($normalizer, 'normalizeRawRow');
        $method->setAccessible(true);

        $columnMap = [
            0 => 'transaction_date',
            1 => 'description',
            2 => 'amount',
            3 => 'reason',
            4 => 'sheet_type',
            5 => 'sheet_category',
        ];

        $row = $method->invoke(
            $normalizer,
            ['2025-03-01', 'Compra Air Doctor, Nicosia, Tarjeta 4176570171221270', '100.50', 'Air Doctor', 'Provider', 'Card'],
            $columnMap,
            2,
        );

        $this->assertSame('Compra Air Doctor, Nicosia, Tarjeta 4176570171221270', $row['name']);
        $this->assertSame('Air Doctor', $row['notes']);
        $this->assertArrayNotHasKey('provider_name', $row);
        $this->assertArrayNotHasKey('patient_name', $row);
    }

    #[Test]
    public function it_counts_formula_category_rows(): void
    {
        $normalizer = new TrxOutStatementNormalizer;

        $rows = collect([
            ['_formula_category' => true],
            ['_formula_category' => false],
            ['_formula_category' => true],
        ]);

        $this->assertSame(2, $normalizer->countFormulaCategoryRows($rows));
    }
}
