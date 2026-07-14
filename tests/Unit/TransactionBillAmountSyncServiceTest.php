<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\Transaction;
use App\Services\TransactionBillAmountSyncService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionBillAmountSyncServiceTest extends TestCase
{
    private TransactionBillAmountSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TransactionBillAmountSyncService;
    }

    #[Test]
    public function distribute_amount_splits_difference_evenly_with_remainder_cents(): void
    {
        $this->assertSame(
            [16.67, 16.67, 16.66],
            $this->service->distributeAmount(50, 3),
        );
    }

    #[Test]
    public function distribute_amount_handles_negative_difference(): void
    {
        $this->assertSame(
            [-16.67, -16.67, -16.66],
            $this->service->distributeAmount(-50, 3),
        );
    }

    #[Test]
    public function resolve_new_bill_totals_uses_new_transaction_total_for_single_bill(): void
    {
        $transaction = new Transaction(['amount' => 1000]);
        $bill = new Bill(['total_amount' => 1000]);
        $bill->id = 5;
        $bill->pivot = (object) ['amount_paid' => 950];
        $transaction->setRelation('bills', collect([$bill]));

        $totals = $this->service->resolveNewBillTotals($transaction, 1040);

        $this->assertSame([5 => 1040.0], $totals);
    }

    #[Test]
    public function resolve_new_bill_totals_divides_difference_across_multiple_bills(): void
    {
        $transaction = new Transaction(['amount' => 1000]);
        $billOne = new Bill(['total_amount' => 600]);
        $billOne->id = 1;
        $billTwo = new Bill(['total_amount' => 400]);
        $billTwo->id = 2;
        $transaction->setRelation('bills', collect([$billOne, $billTwo]));

        $totals = $this->service->resolveNewBillTotals($transaction, 1050);

        $this->assertSame([
            1 => 625.0,
            2 => 425.0,
        ], $totals);
    }

    #[Test]
    public function exceeds_max_difference_rejects_one_hundred_or_more(): void
    {
        $this->assertTrue($this->service->exceedsMaxDifference(1000, 1100));
        $this->assertTrue($this->service->exceedsMaxDifference(1100, 1000));
    }

    #[Test]
    public function exceeds_max_difference_allows_difference_below_one_hundred(): void
    {
        $this->assertFalse($this->service->exceedsMaxDifference(1000, 1099.99));
        $this->assertFalse($this->service->exceedsMaxDifference(1000, 900.01));
    }
}
