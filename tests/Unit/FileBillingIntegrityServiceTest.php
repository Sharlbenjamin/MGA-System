<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\File;
use App\Models\Invoice;
use App\Services\FileBillingIntegrityService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileBillingIntegrityServiceTest extends TestCase
{
    #[Test]
    public function describe_issues_flags_bills_exceeding_invoice_total(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 500,
            invoicesTotal: 400,
        );

        $issues = FileBillingIntegrityService::describeIssues($file);

        $this->assertSame([FileBillingIntegrityService::ISSUE_BILLS_EXCEED_INVOICE], $issues);
    }

    #[Test]
    public function describe_issues_does_not_flag_when_bills_are_below_invoice_totals(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 150,
            invoicesTotal: 300,
        );

        $issues = FileBillingIntegrityService::describeIssues($file);

        $this->assertSame([], $issues);
        $this->assertSame(150.0, FileBillingIntegrityService::marginDeltaFor($file));
    }

    #[Test]
    public function describe_issues_does_not_flag_stale_bill_lines_anymore(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 300,
            invoicesTotal: 500,
        );

        $this->assertSame([], FileBillingIntegrityService::describeIssues($file));
        $this->assertNotContains('stale_bill_lines', array_keys(FileBillingIntegrityService::issueTypeLabels()));
    }

    #[Test]
    public function describe_issues_flags_bill_created_after_invoice(): void
    {
        $invoice = new Invoice([
            'status' => 'Sent',
            'total_amount' => 400,
        ]);
        $invoice->created_at = Carbon::parse('2026-06-01 10:00:00');

        $bill = new Bill(['total_amount' => 300]);
        $bill->created_at = Carbon::parse('2026-06-15 10:00:00');

        $file = new File(['id' => 1, 'mga_reference' => 'MG001AB']);
        $file->setRelation('invoices', collect([$invoice]));
        $file->setRelation('bills', collect([$bill]));
        $file->bills_total_sum = 300;
        $file->invoices_total_sum = 400;

        $issues = FileBillingIntegrityService::describeIssues($file);

        $this->assertContains(FileBillingIntegrityService::ISSUE_BILL_AFTER_INVOICE, $issues);
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
    public function accepted_loss_is_hidden_while_totals_match_snapshot(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 500,
            invoicesTotal: 400,
        );
        $file->accepted_bills_exceed_bills_total = 500;
        $file->accepted_bills_exceed_invoices_total = 400;

        $this->assertTrue(FileBillingIntegrityService::isBillsExceedAccepted($file));
        $this->assertSame([], FileBillingIntegrityService::describeIssues($file));
        $this->assertSame(
            [FileBillingIntegrityService::ISSUE_BILLS_EXCEED_INVOICE],
            FileBillingIntegrityService::describeIssues($file, includeAccepted: true),
        );
    }

    #[Test]
    public function accepted_loss_reopens_when_totals_change(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 550,
            invoicesTotal: 400,
        );
        $file->accepted_bills_exceed_bills_total = 500;
        $file->accepted_bills_exceed_invoices_total = 400;

        $this->assertFalse(FileBillingIntegrityService::isBillsExceedAccepted($file));
        $this->assertContains(
            FileBillingIntegrityService::ISSUE_BILLS_EXCEED_INVOICE,
            FileBillingIntegrityService::describeIssues($file),
        );
    }

    #[Test]
    public function accepted_bill_after_is_hidden_until_a_newer_bill_exists(): void
    {
        $invoice = new Invoice([
            'status' => 'Sent',
            'total_amount' => 400,
        ]);
        $invoice->created_at = Carbon::parse('2026-06-01 10:00:00');

        $bill = new Bill(['total_amount' => 300]);
        $bill->created_at = Carbon::parse('2026-06-15 10:00:00');

        $file = new File([
            'id' => 1,
            'mga_reference' => 'MG001AB',
        ]);
        $file->accepted_bill_after_at = Carbon::parse('2026-06-20 10:00:00');
        $file->setRelation('invoices', collect([$invoice]));
        $file->setRelation('bills', collect([$bill]));
        $file->bills_total_sum = 300;
        $file->invoices_total_sum = 400;

        $this->assertTrue(FileBillingIntegrityService::isBillAfterAccepted($file));
        $this->assertSame([], FileBillingIntegrityService::describeIssues($file));

        $newerBill = new Bill(['total_amount' => 50]);
        $newerBill->created_at = Carbon::parse('2026-06-21 10:00:00');

        $file->setRelation('bills', collect([$bill, $newerBill]));
        $file->bills_total_sum = 350;

        $this->assertFalse(FileBillingIntegrityService::isBillAfterAccepted($file));
        $this->assertContains(
            FileBillingIntegrityService::ISSUE_BILL_AFTER_INVOICE,
            FileBillingIntegrityService::describeIssues($file),
        );
    }

    #[Test]
    public function warning_message_mentions_sent_invoice_when_applicable(): void
    {
        $file = $this->makeFileWithBilling(
            billsTotal: 450,
            invoicesTotal: 500,
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
        );

        $this->assertSame(-100.0, FileBillingIntegrityService::marginDeltaFor($file));
    }

    private function makeFileWithBilling(
        float $billsTotal,
        float $invoicesTotal,
        string $invoiceStatus = 'Sent',
    ): File {
        $file = new File(['id' => 1, 'mga_reference' => 'MG001AB']);
        $bill = new Bill(['total_amount' => $billsTotal]);
        $bill->created_at = Carbon::parse('2026-05-01');
        $invoice = new Invoice([
            'status' => $invoiceStatus,
            'total_amount' => $invoicesTotal,
        ]);
        $invoice->created_at = Carbon::parse('2026-06-01');
        $file->setRelation('bills', collect([$bill]));
        $file->setRelation('invoices', collect([$invoice]));

        $file->bills_total_sum = $billsTotal;
        $file->invoices_total_sum = $invoicesTotal;
        $file->margin_delta = $invoicesTotal - $billsTotal;

        return $file;
    }
}
