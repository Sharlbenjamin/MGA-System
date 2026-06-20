<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionIntegrityService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionIntegrityServiceTest extends TestCase
{
    #[Test]
    public function effective_income_amount_includes_bank_charges(): void
    {
        $transaction = new Transaction([
            'amount' => 100,
            'bank_charges' => 2.50,
        ]);

        $this->assertSame(102.5, TransactionIntegrityService::effectiveIncomeAmountFor($transaction));
    }

    #[Test]
    public function invoice_amount_difference_uses_amount_plus_bank_charges(): void
    {
        $transaction = new Transaction([
            'type' => 'Income',
            'amount' => 100,
            'bank_charges' => 5,
        ]);

        $transaction->setRelation('invoices', collect([
            new Invoice(['total_amount' => 105]),
        ]));

        $this->assertSame(0.0, TransactionIntegrityService::invoiceAmountDifferenceFor($transaction));
        $this->assertFalse(TransactionIntegrityService::hasInvoiceTotalMismatch($transaction));
    }

    #[Test]
    public function invoice_amount_difference_detects_mismatch_when_bank_charges_ignored_would_false_positive(): void
    {
        $transaction = new Transaction([
            'type' => 'Income',
            'amount' => 100,
            'bank_charges' => 5,
        ]);

        $transaction->setRelation('invoices', collect([
            new Invoice(['total_amount' => 100]),
        ]));

        $this->assertTrue(TransactionIntegrityService::hasInvoiceTotalMismatch($transaction));
        $this->assertSame(5.0, TransactionIntegrityService::invoiceAmountDifferenceFor($transaction));
    }
}
