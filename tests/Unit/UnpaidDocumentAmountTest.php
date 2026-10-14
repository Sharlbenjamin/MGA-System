<?php

namespace Tests\Unit;

use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionBillLinkForm;
use App\Filament\Support\TransactionInvoiceLinkForm;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UnpaidDocumentAmountTest extends TestCase
{
    #[Test]
    public function unpaid_amount_uses_the_higher_recorded_payment(): void
    {
        $this->assertSame(60.0, Bill::unpaidFromAmounts(100, 40, 0));
        $this->assertSame(60.0, Bill::unpaidFromAmounts(100, 0, 40));
        $this->assertSame(60.0, Bill::unpaidFromAmounts(100, 30, 40));
        $this->assertSame(100.0, Bill::unpaidFromAmounts(100, 0, 0));
        $this->assertSame(0.0, Bill::unpaidFromAmounts(100, 100, 40));
    }

    #[Test]
    public function partial_bill_paid_amount_defaults_to_the_unpaid_balance(): void
    {
        $bill = new Bill([
            'total_amount' => 1000,
            'paid_amount' => 400,
        ]);

        $this->assertSame(600.0, $bill->amountStillUnpaid());
        $this->assertSame(600.0, TransactionBillLinkForm::defaultPaidAmountForBill($bill, new Transaction));
    }

    #[Test]
    public function partial_invoice_paid_amount_defaults_to_the_unpaid_balance(): void
    {
        $invoice = new Invoice([
            'total_amount' => 250,
            'paid_amount' => 100,
        ]);

        $this->assertSame(150.0, $invoice->amountStillUnpaid());
        $this->assertSame(150.0, TransactionInvoiceLinkForm::defaultPaidAmountForInvoice($invoice, new Transaction));
    }

    #[Test]
    public function transaction_total_sums_line_amounts_instead_of_ignoring_a_new_unpaid_balance(): void
    {
        $total = TransactionResource::sumCreateDocumentPayments(
            [
                ['bill_id' => 1, 'amount_paid' => 1000],
                ['bill_id' => 2, 'amount_paid' => 0],
            ],
            [],
            overrideKey: 'bill_id',
            overrideId: 2,
            overrideAmount: 400,
        );

        $this->assertSame(1400.0, $total);
    }

    #[Test]
    public function transaction_total_is_left_alone_when_no_document_is_selected(): void
    {
        $this->assertNull(TransactionResource::sumCreateDocumentPayments([], []));
        $this->assertNull(TransactionResource::sumCreateDocumentPayments(null, null));
    }
}
