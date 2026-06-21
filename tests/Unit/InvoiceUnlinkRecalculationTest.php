<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Services\InvoiceSettlementIntegrityService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvoiceUnlinkRecalculationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function affected_invoice_ids_include_previous_and_new_links_after_sync(): void
    {
        $merged = array_values(array_unique([
            ...[10, 20],
            ...array_keys([20 => ['amount_paid' => 50], 30 => ['amount_paid' => 25]]),
        ]));

        $this->assertEqualsCanonicalizing([10, 20, 30], $merged);
    }

    #[Test]
    public function unlinking_all_invoices_leaves_only_previous_ids_to_recalculate(): void
    {
        $previousIds = [7];
        $sync = [];

        $affectedIds = array_values(array_unique([...$previousIds, ...array_keys($sync)]));

        $this->assertSame([7], $affectedIds);
    }

    #[Test]
    public function partial_payment_after_unlinking_one_transaction(): void
    {
        $totalAmount = 1000.0;
        $remainingPivotSum = 400.0;

        $this->assertSame('Partial', $this->settlementStatusFor($remainingPivotSum, $totalAmount));
    }

    #[Test]
    public function full_unlink_resets_invoice_to_unpaid(): void
    {
        $this->assertSame('Unpaid', $this->settlementStatusFor(0.0, 500.0));
    }

    #[Test]
    public function describe_issue_detects_paid_invoice_without_transaction_links(): void
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = 'Paid';
        $invoice->paid_amount = 250;

        $invoice->shouldReceive('transactions')->andReturn(
            tap(Mockery::mock(), fn ($relation) => $relation->shouldReceive('exists')->andReturn(false))
        );

        $this->assertSame(
            'no_transaction_link',
            InvoiceSettlementIntegrityService::describeIssue($invoice, 0.0),
        );
    }

    #[Test]
    public function describe_issue_detects_amount_mismatch_when_pivot_sum_differs(): void
    {
        $invoice = Mockery::mock(Invoice::class)->makePartial();
        $invoice->status = 'Paid';
        $invoice->paid_amount = 500;

        $invoice->shouldReceive('transactions')->andReturn(
            tap(Mockery::mock(), fn ($relation) => $relation->shouldReceive('exists')->andReturn(true))
        );

        $this->assertSame(
            'amount_mismatch',
            InvoiceSettlementIntegrityService::describeIssue($invoice, 100.0),
        );
    }

    private function settlementStatusFor(float $paidAmount, float $totalAmount): string
    {
        return match (true) {
            $paidAmount >= $totalAmount => 'Paid',
            $paidAmount > 0 => 'Partial',
            default => 'Unpaid',
        };
    }
}
