<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\DocumentLinkResolver;
use App\Services\LawyerDocumentationExportService;
use App\Services\TransactionDocumentationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LawyerDocumentationExportServiceTest extends TestCase
{
    private LawyerDocumentationExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $linkResolver = $this->createMock(DocumentLinkResolver::class);
        $linkResolver->method('invoiceLinks')->willReturn('');
        $linkResolver->method('trxInLink')->willReturn('');
        $linkResolver->method('transactionReceiptLinks')->willReturn('');

        $this->service = new LawyerDocumentationExportService(
            $linkResolver,
            new TransactionDocumentationService,
        );
    }

    #[Test]
    public function receivable_invoice_rows_use_linked_amount_paid_so_totals_match_transfers(): void
    {
        $transaction = new Transaction([
            'id' => 10,
            'type' => 'Income',
            'amount' => 600,
            'bank_charges' => 5,
        ]);

        $invoiceA = new Invoice(['total_amount' => 1000, 'name' => 'INV-1']);
        $invoiceA->pivot = (object) ['amount_paid' => 400];
        $invoiceA->setRelation('file', null);
        $invoiceA->setRelation('patient', null);

        $invoiceB = new Invoice(['total_amount' => 500, 'name' => 'INV-2']);
        $invoiceB->pivot = (object) ['amount_paid' => 205];
        $invoiceB->setRelation('file', null);
        $invoiceB->setRelation('patient', null);

        $transaction->setRelation('invoices', collect([$invoiceA, $invoiceB]));

        $rows = $this->invokeProtected('buildReceivableInvoiceRows', collect([
            ['transaction' => $transaction, 'invoice' => $invoiceA],
            ['transaction' => $transaction, 'invoice' => $invoiceB],
        ]), 21.0, 0.21, 'country');

        $invoiceTotalAfterIva = array_sum(array_column($rows, 11));
        $summaryRows = $this->invokeProtected('buildReceivablesSummaryRows', collect([$transaction]));

        $this->assertSame(605.0, $invoiceTotalAfterIva);
        $this->assertSame(605.0, $summaryRows[0][4]);
    }

    #[Test]
    public function receivable_invoice_row_falls_back_to_invoice_total_when_pivot_is_zero(): void
    {
        $transaction = new Transaction(['id' => 11, 'type' => 'Income', 'amount' => 250]);
        $invoice = new Invoice(['total_amount' => 250, 'name' => 'INV-3']);
        $invoice->pivot = (object) ['amount_paid' => 0];
        $invoice->setRelation('file', null);
        $invoice->setRelation('patient', null);

        $rows = $this->invokeProtected('buildReceivableInvoiceRows', collect([
            ['transaction' => $transaction, 'invoice' => $invoice],
        ]), 21.0, 0.21, 'country');

        $this->assertSame(250.0, $rows[0][11]);
    }

    private function invokeProtected(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod($this->service, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->service, ...$args);
    }
}
