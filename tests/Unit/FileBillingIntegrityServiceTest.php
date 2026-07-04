<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\FileBillingIntegrityService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileBillingIntegrityServiceTest extends TestCase
{
    #[Test]
    public function describe_issues_flags_bills_exceeding_invoice_total(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 500,
            invoicesTotal: 400,
            invoiceBillLines: 300,
        );

        $issues = FileBillingIntegrityService::describeIssues($file);

        $this->assertContains(FileBillingIntegrityService::ISSUE_BILLS_EXCEED_INVOICE, $issues);
    }

    #[Test]
    public function describe_issues_flags_stale_bill_lines(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 450,
            invoicesTotal: 500,
            invoiceBillLines: 300,
        );

        $issues = FileBillingIntegrityService::describeIssues($file);

        $this->assertContains(FileBillingIntegrityService::ISSUE_STALE_BILL_LINES, $issues);
        $this->assertSame(150.0, FileBillingIntegrityService::billLinesDeltaFor($file));
    }

    #[Test]
    public function describe_issues_is_empty_for_draft_only_invoices(): void
    {
        $file = new File(['id' => 1, 'mga_reference' => 'MG001AB']);
        $file->setRelation('invoices', collect([
            new Invoice(['status' => 'Draft', 'total_amount' => 400]),
        ]));
        $file->setRelation('bills', collect([
            new Bill(['total_amount' => 500]),
        ]));

        $this->assertSame([], FileBillingIntegrityService::describeIssues($file));
        $this->assertFalse(FileBillingIntegrityService::shouldWarnForBillChange($file));
    }

    #[Test]
    public function warning_message_mentions_sent_invoice_when_applicable(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 450,
            invoicesTotal: 500,
            invoiceBillLines: 300,
            invoiceStatus: 'Paid',
        );

        $message = FileBillingIntegrityService::warningForBillChange($file, 'update');

        $this->assertNotNull($message);
        $this->assertStringContainsString('MG001AB', $message);
        $this->assertStringContainsString('after the invoice was sent', $message);
        $this->assertTrue(FileBillingIntegrityService::shouldWarnForBillChange($file));
    }

    #[Test]
    public function margin_delta_is_invoice_total_minus_bills_total(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 500,
            invoicesTotal: 400,
            invoiceBillLines: 300,
        );

        $this->assertSame(-100.0, FileBillingIntegrityService::marginDeltaFor($file));
    }

    private function makeFileWithBilling(
        float $billsTotal,
        float $invoicesTotal,
        float $invoiceBillLines,
        string $invoiceStatus = 'Sent',
    ): File {
        $file = new File(['id' => 1, 'mga_reference' => 'MG001AB']);
        $file->setRelation('bills', collect([
            new Bill(['total_amount' => $billsTotal]),
        ]));
        $file->setRelation('invoices', collect([
            new Invoice([
                'status' => $invoiceStatus,
                'total_amount' => $invoicesTotal,
            ]),
        ]));

        $file->bills_total_sum = $billsTotal;
        $file->invoices_total_sum = $invoicesTotal;
        $file->invoice_bill_lines_sum = $invoiceBillLines;
        $file->margin_delta = $invoicesTotal - $billsTotal;
        $file->bill_lines_delta = $billsTotal - $invoiceBillLines;

        return $file;
    }
}
