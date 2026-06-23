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
    public function invoice_amount_difference_uses_pivot_amount_paid_not_invoice_total(): void
    {
        $transaction = new Transaction([
            'type' => 'Income',
            'amount' => 100,
            'bank_charges' => 5,
        ]);

        $invoice = new Invoice(['total_amount' => 500]);
        $invoice->pivot = (object) ['amount_paid' => 105];
        $transaction->setRelation('invoices', collect([$invoice]));

        $this->assertSame(0.0, TransactionIntegrityService::invoiceAmountDifferenceFor($transaction));
        $this->assertFalse(TransactionIntegrityService::hasInvoiceTotalMismatch($transaction));
    }

    #[Test]
    public function partial_invoice_link_does_not_mismatch_when_pivot_matches_transaction(): void
    {
        $transaction = new Transaction([
            'type' => 'Income',
            'amount' => 600,
            'bank_charges' => 0,
        ]);

        $invoice = new Invoice(['total_amount' => 1000]);
        $invoice->pivot = (object) ['amount_paid' => 600];
        $transaction->setRelation('invoices', collect([$invoice]));

        $this->assertSame(600.0, TransactionIntegrityService::invoicesPaidTotalFor($transaction));
        $this->assertFalse(TransactionIntegrityService::hasInvoiceTotalMismatch($transaction));
    }

    #[Test]
    public function invoice_amount_difference_detects_mismatch_when_pivot_does_not_match_effective_amount(): void
    {
        $transaction = new Transaction([
            'type' => 'Income',
            'amount' => 100,
            'bank_charges' => 5,
        ]);

        $invoice = new Invoice(['total_amount' => 100]);
        $invoice->pivot = (object) ['amount_paid' => 100];
        $transaction->setRelation('invoices', collect([$invoice]));

        $this->assertTrue(TransactionIntegrityService::hasInvoiceTotalMismatch($transaction));
        $this->assertSame(5.0, TransactionIntegrityService::invoiceAmountDifferenceFor($transaction));
    }

    #[Test]
    public function bill_amount_difference_uses_pivot_amount_paid(): void
    {
        $transaction = new Transaction([
            'type' => 'Outflow',
            'amount' => 250,
        ]);

        $bill = new Bill(['total_amount' => 300]);
        $bill->pivot = (object) ['amount_paid' => 250];
        $transaction->setRelation('bills', collect([$bill]));

        $this->assertSame(250.0, TransactionIntegrityService::billsPaidTotalFor($transaction));
        $this->assertFalse(TransactionIntegrityService::hasBillTotalMismatch($transaction));
    }

    #[Test]
    public function bill_amount_difference_detects_mismatch(): void
    {
        $transaction = new Transaction([
            'type' => 'Outflow',
            'amount' => 250,
            'related_type' => 'Provider',
            'related_id' => 1,
        ]);

        $bill = new Bill(['total_amount' => 300]);
        $bill->pivot = (object) ['amount_paid' => 200];
        $transaction->setRelation('bills', collect([$bill]));

        $this->assertTrue(TransactionIntegrityService::hasBillTotalMismatch($transaction));
        $this->assertSame(50.0, TransactionIntegrityService::billAmountDifferenceFor($transaction));

        $withProviderOnly = new Transaction([
            'type' => 'Outflow',
            'related_type' => 'Provider',
            'related_id' => 1,
        ]);
        $withProviderOnly->setRelation('bills', collect());

        $this->assertSame('No bills', TransactionIntegrityService::outflowLinkingIssueLabel($withProviderOnly));
        $this->assertSame('Amount mismatch', TransactionIntegrityService::outflowLinkingIssueLabel($transaction));
    }

    #[Test]
    public function outflow_linking_issue_label_flags_missing_provider(): void
    {
        $transaction = new Transaction([
            'type' => 'Outflow',
        ]);
        $transaction->setRelation('bills', collect());

        $this->assertSame('No provider, No bills', TransactionIntegrityService::outflowLinkingIssueLabel($transaction));
    }
}
