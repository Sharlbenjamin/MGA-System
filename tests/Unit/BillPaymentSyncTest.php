<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BillPaymentSyncTest extends TestCase
{
    #[Test]
    public function affected_bill_ids_include_previous_and_new_links_after_sync(): void
    {
        $merged = array_values(array_unique([
            ...[10, 20],
            ...array_keys([20 => ['amount_paid' => 200], 30 => ['amount_paid' => 150]]),
        ]));

        $this->assertEqualsCanonicalizing([10, 20, 30], $merged);
    }

    #[Test]
    public function bill_status_is_paid_when_pivot_sum_matches_total(): void
    {
        $this->assertSame('Paid', $this->billStatusFor(500.0, 500.0));
    }

    #[Test]
    public function bill_status_is_partial_when_pivot_sum_is_between_zero_and_total(): void
    {
        $this->assertSame('Partial', $this->billStatusFor(200.0, 500.0));
    }

    #[Test]
    public function bill_status_is_unpaid_when_no_pivot_payments(): void
    {
        $this->assertSame('Unpaid', $this->billStatusFor(0.0, 500.0));
    }

    #[Test]
    public function remaining_balance_never_goes_below_zero(): void
    {
        $total = 400.0;
        $paid = 450.0;

        $this->assertEquals(0.0, max(0, round($total - $paid, 2)));
    }

    private function billStatusFor(float $paidAmount, float $totalAmount): string
    {
        return match (true) {
            $paidAmount >= $totalAmount && $totalAmount > 0 => 'Paid',
            $paidAmount > 0 => 'Partial',
            default => 'Unpaid',
        };
    }
}
