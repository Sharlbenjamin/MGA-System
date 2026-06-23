<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\TransactionDocumentationStatsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionDocumentationStatsRefundedPaymentTest extends TestCase
{
    #[Test]
    public function refunded_payment_is_available_for_outflow_and_expense(): void
    {
        $outflowOptions = TransactionDocumentationStatsService::categoryOptionsFor('Outflow', 'Provider');
        $expenseOptions = TransactionDocumentationStatsService::categoryOptionsFor('Expense', 'Rent');

        $this->assertArrayHasKey('refunded_payment', $outflowOptions);
        $this->assertArrayHasKey('refunded_payment', $expenseOptions);
        $this->assertSame('Refunded Payment', $outflowOptions['refunded_payment']);
    }

    #[Test]
    public function refunded_payment_is_not_available_for_income(): void
    {
        $incomeOptions = TransactionDocumentationStatsService::categoryOptionsFor('Income', 'Client');

        $this->assertArrayNotHasKey('refunded_payment', $incomeOptions);
        $this->assertArrayHasKey('refund', $incomeOptions);
    }
}
