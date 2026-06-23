<?php

namespace Tests\Unit;

use App\Http\Controllers\TaxesExportController;
use App\Models\Transaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TaxesExportRefundedPaymentTest extends TestCase
{
    #[Test]
    public function refunded_payment_row_uses_comment_as_invoice_number_and_source_label(): void
    {
        $transaction = new Transaction;
        $transaction->setRawAttributes([
            'type' => 'Outflow',
            'documentation_category' => 'refunded_payment',
            'name' => 'Bank ref 12345',
            'notes' => 'Refunded by provider on 2026-03-01',
            'amount' => 150,
            'date' => '2026-02-10',
            'related_type' => 'Provider',
            'related_id' => null,
        ], true);

        $row = $this->buildTransactionPaymentRow($transaction);

        $this->assertSame('Payment', $row[0]);
        $this->assertSame('2026-02-10', $row[1]);
        $this->assertSame('Refunded by provider on 2026-03-01', $row[2]);
        $this->assertSame('Refunded Payment', $row[12]);
        $this->assertSame('', $row[13]);
    }

    #[Test]
    public function normal_expense_row_uses_transaction_name_and_notes_column(): void
    {
        $transaction = new Transaction;
        $transaction->setRawAttributes([
            'type' => 'Expense',
            'documentation_category' => 'expense_payment',
            'name' => 'Office rent',
            'notes' => 'Monthly rent',
            'amount' => 500,
            'date' => '2026-01-15',
        ], true);

        $row = $this->buildTransactionPaymentRow($transaction);

        $this->assertSame('Office rent', $row[2]);
        $this->assertSame('Transaction', $row[12]);
        $this->assertSame('Monthly rent', $row[13]);
    }

    /**
     * @return array<int, mixed>
     */
    private function buildTransactionPaymentRow(Transaction $transaction): array
    {
        $controller = new TaxesExportController;
        $method = new ReflectionMethod(TaxesExportController::class, 'buildTransactionPaymentRow');
        $method->setAccessible(true);

        return $method->invoke($controller, $transaction);
    }
}
